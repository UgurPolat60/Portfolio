/* webserv - the socket layer: listen, accept, poll, read, write.
   Everything that speaks HTTP lives in http.c; this file only moves bytes. */

#include <errno.h>
#include <fcntl.h>
#include <netinet/in.h>
#include <poll.h>
#include <signal.h>
#include <stdint.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <unistd.h>

#include "webserv.h"

static t_conn					g_conns[MAX_CONNS];
static struct pollfd			g_pfds[MAX_CONNS + 1];
static int						g_nconn;
static volatile sig_atomic_t	g_stop;

static void	on_signal(int sig)
{
	(void)sig;
	g_stop = 1;
}

static int	set_nonblock(int fd)
{
	int	flags;

	flags = fcntl(fd, F_GETFL, 0);
	if (flags < 0)
		return (-1);
	return (fcntl(fd, F_SETFL, flags | O_NONBLOCK));
}

static int	listen_on(int port)
{
	struct sockaddr_in	addr;
	int					fd;
	int					yes;

	yes = 1;
	fd = socket(AF_INET, SOCK_STREAM, 0);
	if (fd < 0)
		return (-1);
	/* without SO_REUSEADDR the port sits in TIME_WAIT for a minute after
	   every restart, which makes developing against it miserable */
	setsockopt(fd, SOL_SOCKET, SO_REUSEADDR, &yes, sizeof yes);
	memset(&addr, 0, sizeof addr);
	addr.sin_family = AF_INET;
	addr.sin_addr.s_addr = htonl(INADDR_ANY);
	addr.sin_port = htons((uint16_t)port);
	if (bind(fd, (struct sockaddr *)&addr, sizeof addr) < 0
		|| listen(fd, 128) < 0 || set_nonblock(fd) < 0)
	{
		close(fd);
		return (-1);
	}
	return (fd);
}

static void	conn_close(int i)
{
	close(g_conns[i].fd);
	free(g_conns[i].out);
	g_conns[i] = g_conns[g_nconn - 1];
	g_nconn--;
}

static void	conn_accept(int lfd)
{
	int	fd;

	fd = accept(lfd, NULL, NULL);
	if (fd < 0)
		return ;
	if (g_nconn >= MAX_CONNS || set_nonblock(fd) < 0)
	{
		close(fd);
		return ;
	}
	memset(&g_conns[g_nconn], 0, sizeof(t_conn));
	g_conns[g_nconn].fd = fd;
	g_nconn++;
}

/* -1 means: this connection is finished, drop it */
static int	conn_read(t_conn *c)
{
	ssize_t	n;
	size_t	room;

	room = sizeof c->in - 1 - c->in_len;
	if (room == 0)
		return (-1);
	n = read(c->fd, c->in + c->in_len, room);
	if (n < 0)
	{
		if (errno == EAGAIN || errno == EWOULDBLOCK)
			return (0);
		return (-1);
	}
	if (n == 0)
		return (-1);
	c->in_len += (size_t)n;
	return (http_try(c));
}

static int	conn_write(t_conn *c)
{
	ssize_t	n;

	n = write(c->fd, c->out + c->out_sent, c->out_len - c->out_sent);
	if (n < 0)
	{
		if (errno == EAGAIN || errno == EWOULDBLOCK)
			return (0);
		return (-1);
	}
	c->out_sent += (size_t)n;
	if (c->out_sent < c->out_len)
		return (0);
	free(c->out);
	c->out = NULL;
	c->out_len = 0;
	c->out_sent = 0;
	if (c->close_after)
		return (-1);
	/* keep-alive: a pipelined request may already be sitting in the buffer */
	return (http_try(c));
}

static void	serve_forever(int lfd)
{
	int		i;
	short	re;
	int		dead;

	while (!g_stop)
	{
		g_pfds[0].fd = lfd;
		g_pfds[0].events = POLLIN;
		i = -1;
		while (++i < g_nconn)
		{
			g_pfds[i + 1].fd = g_conns[i].fd;
			g_pfds[i + 1].events = g_conns[i].out ? POLLOUT : POLLIN;
		}
		if (poll(g_pfds, (nfds_t)(g_nconn + 1), -1) < 0)
		{
			if (errno == EINTR)
				continue ;
			break ;
		}
		/* walk backwards: conn_close() fills the hole with the last entry */
		i = g_nconn;
		while (--i >= 0)
		{
			re = g_pfds[i + 1].revents;
			dead = 0;
			if (re & POLLOUT)
				dead = conn_write(&g_conns[i]) < 0;
			else if (re & POLLIN)
				dead = conn_read(&g_conns[i]) < 0;
			else if (re & (POLLERR | POLLHUP | POLLNVAL))
				dead = 1;
			if (dead)
				conn_close(i);
		}
		/* accept last, so the indexes above stayed valid all the way through */
		if (g_pfds[0].revents & POLLIN)
			conn_accept(lfd);
	}
}

int	main(int argc, char **argv)
{
	int			port;
	const char	*docroot;
	int			lfd;

	port = (argc > 1) ? atoi(argv[1]) : 8080;
	docroot = (argc > 2) ? argv[2] : "www";
	setvbuf(stdout, NULL, _IOLBF, 0);
	/* a client that hangs up mid-response must not kill the whole server */
	signal(SIGPIPE, SIG_IGN);
	signal(SIGINT, on_signal);
	signal(SIGTERM, on_signal);
	if (port < 1 || port > 65535)
	{
		fprintf(stderr, "webserv: port out of range: %s\n", argv[1]);
		return (1);
	}
	if (http_init(docroot, "contact.log") < 0)
	{
		fprintf(stderr, "webserv: cannot open docroot '%s'\n", docroot);
		return (1);
	}
	lfd = listen_on(port);
	if (lfd < 0)
	{
		perror("webserv");
		return (1);
	}
	printf("webserv listening on http://localhost:%d   docroot: %s\n",
		port, docroot);
	serve_forever(lfd);
	while (g_nconn > 0)
		conn_close(g_nconn - 1);
	close(lfd);
	printf("webserv stopped.\n");
	return (0);
}
