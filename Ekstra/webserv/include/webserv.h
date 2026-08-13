#ifndef WEBSERV_H
# define WEBSERV_H

# include <stddef.h>

/* Ceilings. A request that does not fit is answered with an error, never
   grown into: a fixed budget per connection is what keeps a public server
   from being turned into a memory bomb by anyone who can open a socket. */
# define REQ_MAX	8192	/* request line + headers */
# define BODY_MAX	4096	/* POST body */
# define MAX_CONNS	256
# define IN_MAX		(REQ_MAX + BODY_MAX + 1)
/* most bytes one 206 may carry: the client just asks for the next slice */
# define RANGE_MAX	(2 * 1024 * 1024)

typedef struct s_conn
{
	int		fd;
	char	in[IN_MAX];		/* bytes read, not yet answered */
	size_t	in_len;
	char	*out;			/* full response: headers + body, malloc'd */
	size_t	out_len;
	size_t	out_sent;
	int		close_after;	/* no keep-alive: drop once out is flushed */
	int		status;			/* last status code, for the access log */
}	t_conn;

int		http_init(const char *docroot, const char *contact_log);
int		http_try(t_conn *c);

#endif
