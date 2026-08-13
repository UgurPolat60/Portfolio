/* webserv - the HTTP layer: parse a request, decide what it asks for,
   build the answer. Nothing here touches sockets; main.c does that. */

#define _GNU_SOURCE

#include <fcntl.h>
#include <limits.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <strings.h>
#include <sys/stat.h>
#include <time.h>
#include <unistd.h>

#include "webserv.h"

static char			g_root[PATH_MAX];	/* docroot, fully resolved */
static size_t		g_root_len;
static const char	*g_log;

int	http_init(const char *docroot, const char *contact_log)
{
	if (!realpath(docroot, g_root))
		return (-1);
	g_root_len = strlen(g_root);
	g_log = contact_log;
	return (0);
}

/* ------------------------------------------------------------------ files */

static int	file_size(const char *path, size_t *size)
{
	struct stat	st;

	if (stat(path, &st) < 0 || !S_ISREG(st.st_mode))
		return (-1);
	*size = (size_t)st.st_size;
	return (0);
}

/* Reads `want` bytes starting at `off`; want == 0 means "to the end".
   ponytail: still one malloc per response, but a ranged request now costs only
   the slice it asked for, not the whole file. sendfile() is the next step if
   this ever serves real video traffic. */
static char	*read_file(const char *path, size_t off, size_t want,
		size_t *out_len)
{
	struct stat	st;
	char		*buf;
	size_t		n;
	ssize_t		r;
	int			fd;

	fd = open(path, O_RDONLY);
	if (fd < 0)
		return (NULL);
	if (fstat(fd, &st) < 0 || !S_ISREG(st.st_mode) || off > (size_t)st.st_size)
	{
		close(fd);
		return (NULL);
	}
	n = (size_t)st.st_size - off;
	if (want && want < n)
		n = want;
	buf = malloc(n + 1);
	if (!buf)
	{
		close(fd);
		return (NULL);
	}
	*out_len = 0;
	while (*out_len < n)
	{
		r = pread(fd, buf + *out_len, n - *out_len, (off_t)(off + *out_len));
		if (r <= 0)
			break ;
		*out_len += (size_t)r;
	}
	close(fd);
	buf[*out_len] = '\0';
	return (buf);
}

static const char	*mime_for(const char *path)
{
	static const struct
	{
		const char	*ext;
		const char	*type;
	}			table[] = {
	{".html", "text/html; charset=utf-8"},
	{".css", "text/css; charset=utf-8"},
	{".js", "application/javascript; charset=utf-8"},
	{".json", "application/json"},
	{".svg", "image/svg+xml"},
	{".png", "image/png"},
	{".jpg", "image/jpeg"},
	{".jpeg", "image/jpeg"},
	{".gif", "image/gif"},
	{".webp", "image/webp"},
	{".ico", "image/x-icon"},
	{".mp4", "video/mp4"},
	{".webm", "video/webm"},
	{".woff2", "font/woff2"},
	{".txt", "text/plain; charset=utf-8"},
	{NULL, NULL}
	};
	const char	*dot;
	int			i;

	dot = strrchr(path, '.');
	if (dot)
	{
		i = -1;
		while (table[++i].ext)
			if (strcasecmp(dot, table[i].ext) == 0)
				return (table[i].type);
	}
	return ("application/octet-stream");
}

/* ------------------------------------------------------------- responses */

/* `extra` is zero or more complete "Name: value\r\n" lines, or NULL */
static int	resp(t_conn *c, int code, const char *reason, const char *ctype,
		const char *body, size_t blen, int head_only, const char *extra)
{
	char	hdr[512];
	int		hlen;

	c->status = code;
	hlen = snprintf(hdr, sizeof hdr,
			"HTTP/1.1 %d %s\r\n"
			"Server: webserv/1.0\r\n"
			"Content-Type: %s\r\n"
			"Content-Length: %zu\r\n"
			"%s"
			"Connection: %s\r\n"
			"\r\n",
			code, reason, ctype, blen, extra ? extra : "",
			c->close_after ? "close" : "keep-alive");
	/* snprintf reports the length it wanted: a truncated header is a broken
	   response, not something to send half of */
	if (hlen < 0 || hlen >= (int)sizeof hdr)
		return (-1);
	/* a HEAD answer carries the headers of the GET but not one byte of body */
	if (head_only)
		blen = 0;
	c->out = malloc((size_t)hlen + blen);
	if (!c->out)
		return (-1);
	memcpy(c->out, hdr, (size_t)hlen);
	if (blen)
		memcpy(c->out + hlen, body, blen);
	c->out_len = (size_t)hlen + blen;
	c->out_sent = 0;
	return (0);
}

static int	resp_err(t_conn *c, int code, const char *reason)
{
	char	body[320];
	int		n;

	n = snprintf(body, sizeof body,
			"<!doctype html><meta charset=\"utf-8\"><title>%d %s</title>"
			"<body style=\"background:#0a0c10;color:#e6e9ef;"
			"font:16px/1.6 system-ui,sans-serif;padding:4rem 2rem\">"
			"<h1 style=\"margin:0 0 .5rem\">%d</h1><p>%s</p>"
			"<p><a style=\"color:#66d9a0\" href=\"/\">&larr; home</a></p>",
			code, reason, code, reason);
	/* an error means we stopped trusting this stream: do not keep it alive */
	c->close_after = 1;
	return (resp(c, code, reason, "text/html; charset=utf-8",
			body, (size_t)n, 0, NULL));
}

static int	not_found(t_conn *c, int head_only)
{
	char	path[PATH_MAX + 16];
	char	*body;
	size_t	len;
	int		rc;

	snprintf(path, sizeof path, "%s/404.html", g_root);
	body = read_file(path, 0, 0, &len);
	if (!body)
		return (resp_err(c, 404, "Not Found"));
	rc = resp(c, 404, "Not Found", "text/html; charset=utf-8",
			body, len, head_only, NULL);
	free(body);
	return (rc);
}

/* ---------------------------------------------------------------- parsing */

static int	hexval(int ch)
{
	if (ch >= '0' && ch <= '9')
		return (ch - '0');
	if (ch >= 'a' && ch <= 'f')
		return (ch - 'a' + 10);
	if (ch >= 'A' && ch <= 'F')
		return (ch - 'A' + 10);
	return (-1);
}

/* %41 -> 'A', in place. '+' is a space only in form bodies, never in paths. */
static void	url_decode(char *s, int plus_is_space)
{
	char	*w;
	int		hi;
	int		lo;

	w = s;
	while (*s)
	{
		hi = (*s == '%') ? hexval((unsigned char)s[1]) : -1;
		lo = (hi >= 0) ? hexval((unsigned char)s[2]) : -1;
		if (lo >= 0)
		{
			*w++ = (char)((hi << 4) | lo);
			s += 3;
		}
		else if (*s == '+' && plus_is_space)
		{
			*w++ = ' ';
			s++;
		}
		else
			*w++ = *s++;
	}
	*w = '\0';
}

/* p points at the first header line; header names are case-insensitive */
static const char	*hdr_find(const char *p, const char *name)
{
	size_t	nlen;

	nlen = strlen(name);
	while (*p)
	{
		if (strncasecmp(p, name, nlen) == 0 && p[nlen] == ':')
		{
			p += nlen + 1;
			while (*p == ' ' || *p == '\t')
				p++;
			return (p);
		}
		p = strchr(p, '\n');
		if (!p)
			break ;
		p++;
	}
	return (NULL);
}

/* ----------------------------------------------------------------- ranges */

/* One range only: "bytes=500-", "bytes=0-1023", "bytes=-500". Anything richer
   (several ranges in one request) is answered with the whole file instead,
   which the RFC allows and no browser asks for on a <video>.
   1 = usable range, 0 = ignore it, -1 = it starts past the end of the file. */
static int	parse_range(const char *v, size_t size, size_t *off, size_t *len)
{
	char	*end;
	size_t	first;
	size_t	last;

	if (strncasecmp(v, "bytes=", 6) != 0 || strchr(v, ','))
		return (0);
	v += 6;
	if (*v == '-')
	{
		last = strtoul(v + 1, &end, 10);
		if (end == v + 1 || last == 0 || size == 0)
			return (0);
		*off = (last >= size) ? 0 : size - last;
		*len = size - *off;
		return (1);
	}
	first = strtoul(v, &end, 10);
	if (end == v || *end != '-')
		return (0);
	v = end + 1;
	last = strtoul(v, &end, 10);
	if (end == v || last >= size)
		last = size - 1;
	if (first >= size || last < first)
		return (-1);
	*off = first;
	*len = last - first + 1;
	return (1);
}

/* A <video> can only seek if the server answers Range requests: without them
   the browser has no way to ask for the middle of the file, so every jump
   restarts playback from zero. Hence 206 — and a ceiling on what one response
   carries, because the client will simply ask for the next slice.
   Returns 1 when the header was not usable, so the caller sends the whole
   file as an ordinary 200. */
static int	serve_range(t_conn *c, const char *real, const char *range,
		int head_only)
{
	char	extra[160];
	char	*body;
	size_t	size;
	size_t	off;
	size_t	len;
	int		rc;

	if (file_size(real, &size) < 0)
		return (1);
	rc = parse_range(range, size, &off, &len);
	if (rc == 0)
		return (1);
	if (rc < 0)
	{
		snprintf(extra, sizeof extra,
			"Accept-Ranges: bytes\r\nContent-Range: bytes */%zu\r\n", size);
		return (resp(c, 416, "Range Not Satisfiable",
				"text/plain; charset=utf-8", "", 0, head_only, extra));
	}
	if (len > RANGE_MAX)
		len = RANGE_MAX;
	body = read_file(real, off, len, &len);
	if (!body || len == 0)
		return (free(body), 1);
	snprintf(extra, sizeof extra,
		"Accept-Ranges: bytes\r\nContent-Range: bytes %zu-%zu/%zu\r\n",
		off, off + len - 1, size);
	rc = resp(c, 206, "Partial Content", mime_for(real),
			body, len, head_only, extra);
	return (free(body), rc);
}

/* ------------------------------------------------------------ GET / HEAD */

static int	serve_file(t_conn *c, char *target, int head_only,
		const char *range)
{
	char	path[PATH_MAX];
	char	real[PATH_MAX];
	char	*query;
	char	*body;
	size_t	len;
	int		rc;

	query = strchr(target, '?');
	if (query)
		*query = '\0';
	url_decode(target, 0);
	if (target[0] != '/')
		return (resp_err(c, 400, "Bad Request"));
	if (snprintf(path, sizeof path, "%s%s%s", g_root, target,
			target[strlen(target) - 1] == '/' ? "index.html" : "")
		>= (int)sizeof path)
		return (resp_err(c, 414, "URI Too Long"));
	if (!realpath(path, real))
		return (not_found(c, head_only));
	/* THE security boundary. realpath() has collapsed every ".." and symlink,
	   so whatever the client dressed the path up as, the truth is in `real`:
	   if it does not sit inside the docroot, it is not ours to hand out. */
	if (strncmp(real, g_root, g_root_len) != 0
		|| (real[g_root_len] != '/' && real[g_root_len] != '\0'))
		return (resp_err(c, 403, "Forbidden"));
	if (range)
	{
		rc = serve_range(c, real, range, head_only);
		if (rc <= 0)
			return (rc);
	}
	body = read_file(real, 0, 0, &len);
	if (!body)
		return (not_found(c, head_only));
	rc = resp(c, 200, "OK", mime_for(real), body, len, head_only,
			"Accept-Ranges: bytes\r\n");
	free(body);
	return (rc);
}

/* ------------------------------------------------------------------ POST */

/* Everything below arrives from the network, so it is guilty until cleaned:
   control bytes become spaces (a newline would forge a second log entry)
   and the field is cut short, because nobody needs a 4 KB name. */
static void	sanitize(char *s)
{
	size_t	i;

	i = 0;
	while (s[i] && i < 500)
	{
		if ((unsigned char)s[i] < 0x20 || (unsigned char)s[i] == 0x7f)
			s[i] = ' ';
		i++;
	}
	s[i] = '\0';
}

static char	*form_field(const char *body, const char *key)
{
	size_t		klen;
	const char	*end;
	char		*val;

	klen = strlen(key);
	while (*body)
	{
		if (strncmp(body, key, klen) == 0 && body[klen] == '=')
		{
			body += klen + 1;
			end = strchr(body, '&');
			if (!end)
				end = body + strlen(body);
			val = strndup(body, (size_t)(end - body));
			if (!val)
				return (NULL);
			url_decode(val, 1);
			sanitize(val);
			return (val);
		}
		body = strchr(body, '&');
		if (!body)
			break ;
		body++;
	}
	return (NULL);
}

static int	handle_contact(t_conn *c, char *body)
{
	char	*field[3];
	char	stamp[32];
	time_t	now;
	FILE	*f;
	int		i;

	field[0] = form_field(body, "name");
	field[1] = form_field(body, "email");
	field[2] = form_field(body, "message");
	if (!field[0] || !field[1] || !field[2] || !*field[0] || !*field[2])
	{
		i = -1;
		while (++i < 3)
			free(field[i]);
		return (resp_err(c, 400, "Bad Request"));
	}
	f = fopen(g_log, "a");
	if (f)
	{
		now = time(NULL);
		strftime(stamp, sizeof stamp, "%Y-%m-%d %H:%M:%S", localtime(&now));
		fprintf(f, "[%s] %s <%s>: %s\n", stamp, field[0], field[1], field[2]);
		fclose(f);
	}
	printf("   -> contact: %s <%s>\n", field[0], field[1]);
	i = -1;
	while (++i < 3)
		free(field[i]);
	return (resp(c, 200, "OK", "application/json", "{\"ok\":true}", 11, 0, NULL));
}

/* ----------------------------------------------------------------- router */

static int	route(t_conn *c, char *req, size_t head_len)
{
	char		*method;
	char		*target;
	char		*version;
	char		*headers;
	const char	*value;
	int			rc;

	headers = strstr(req, "\r\n");
	if (!headers)
		return (resp_err(c, 400, "Bad Request"));
	headers += 2;
	method = req;
	target = strchr(req, ' ');
	if (!target || target > headers)
		return (resp_err(c, 400, "Bad Request"));
	*target++ = '\0';
	version = strchr(target, ' ');
	if (!version || version > headers)
		return (resp_err(c, 400, "Bad Request"));
	*version++ = '\0';
	/* HTTP/1.1 keeps the connection open by default, 1.0 does not */
	c->close_after = (strncmp(version, "HTTP/1.1", 8) != 0);
	value = hdr_find(headers, "Connection");
	if (value && strncasecmp(value, "close", 5) == 0)
		c->close_after = 1;
	else if (value && strncasecmp(value, "keep-alive", 10) == 0)
		c->close_after = 0;
	if (!strcmp(method, "GET") || !strcmp(method, "HEAD"))
		rc = serve_file(c, target, !strcmp(method, "HEAD"),
				hdr_find(headers, "Range"));
	else if (!strcmp(method, "POST") && !strcmp(target, "/contact"))
		rc = handle_contact(c, req + head_len);
	else if (!strcmp(method, "POST"))
		rc = resp_err(c, 404, "Not Found");
	else
		rc = resp_err(c, 501, "Not Implemented");
	printf("%-4s %-28s %d\n", method, target, c->status);
	return (rc);
}

/* Called after every read. TCP is a stream, not a message queue: a request
   can arrive in five pieces, or five requests can arrive in one piece. So
   answer only what is complete, and keep the rest for the next round. */
int	http_try(t_conn *c)
{
	char		*head_end;
	size_t		head_len;
	size_t		body_len;
	size_t		total;
	const char	*value;
	int			rc;

	if (c->out || c->in_len == 0)
		return (0);
	head_end = memmem(c->in, c->in_len, "\r\n\r\n", 4);
	if (!head_end)
	{
		if (c->in_len >= REQ_MAX)
			return (resp_err(c, 431, "Request Header Fields Too Large"));
		return (0);
	}
	head_len = (size_t)(head_end - c->in) + 4;
	c->in[head_len - 2] = '\0';
	value = hdr_find(c->in, "Content-Length");
	body_len = value ? strtoul(value, NULL, 10) : 0;
	if (body_len > BODY_MAX)
		return (resp_err(c, 413, "Payload Too Large"));
	total = head_len + body_len;
	if (c->in_len < total)
	{
		c->in[head_len - 2] = '\r';
		return (0);
	}
	c->in[total] = '\0';
	rc = route(c, c->in, head_len);
	memmove(c->in, c->in + total, c->in_len - total);
	c->in_len -= total;
	return (rc);
}
