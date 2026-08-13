# webserv

An HTTP/1.1 server written from scratch in C, and the portfolio site it serves.

No frameworks. No libraries beyond libc and POSIX sockets. One `poll()` loop,
two source files, one binary.

```
make
./webserv 8080 www
```

Then open <http://localhost:8080>.

---

## Why

Every website runs on a program that listens on a port, reads what the browser
sends, and writes back an answer. Nearly everyone rents that program — nginx,
Apache, whatever the host provides — and treats it as a black box.

This is that program, written by hand, so that it isn't one.

## What it does

- **HTTP/1.1** request parsing: request line, headers, body
- **GET / HEAD / POST**, and an honest `501` for everything else
- **Static files** from a document root — HTML, CSS, images and MP4 video —
  with content types by extension
- **Directory indexes**, so `/projects/rage-attack/` finds its own `index.html`
- **Persistent connections** (keep-alive) and pipelined requests
- **Non-blocking I/O** — one `poll()` loop, up to 256 concurrent clients, no
  threads and no forking
- **`POST /contact`** — the contact form on the site, appended to `contact.log`
- **Path containment** — a request can't be dressed up to reach outside the
  document root
- Correct status codes: `200 400 403 404 413 414 431 501`

## Architecture

Two files, split by the only line that matters:

| File | Responsibility |
|---|---|
| `src/main.c` | Sockets. Listen, accept, poll, read, write. Knows nothing about HTTP. |
| `src/http.c` | HTTP. Parse, route, build responses. Never touches a socket. |

The whole server is a state machine over a fixed array of connections:

```
       ┌─────────────────── poll() ───────────────────┐
       │                                              │
   listen fd readable          client fd readable/writable
       │                                              │
   accept()                     read()  ──►  http_try()
       │                                          │
   add to conns[]                    request complete?
                                       │            │
                                      no           yes
                                       │            │
                                 wait for more   build response
                                                    │
                                            write() until drained
                                                    │
                                        keep-alive? ─┴─► reuse / close
```

### The part that is actually hard

TCP is a byte stream, not a message queue. A single HTTP request can arrive in
five pieces, and five requests can arrive in one piece. So `http_try()` is
called after every read and answers exactly one question: *is there a complete
request in this buffer yet?* If not, it returns and waits. If there is, it
answers it and shifts whatever came after it to the front of the buffer.

Getting that wrong is how servers hang, truncate, or answer the wrong request.

### The security boundary

`GET /../../../../etc/passwd` is the oldest attack on a web server, and it has
a hundred disguises: `%2e%2e`, symlinks, nested paths that walk back out.

Rather than trying to recognise every disguise, the server doesn't inspect the
requested path at all. It hands the path to `realpath()`, which collapses every
`..` and follows every symlink, and then checks one thing:

```c
if (strncmp(real, g_root, g_root_len) != 0
    || (real[g_root_len] != '/' && real[g_root_len] != '\0'))
    return (resp_err(c, 403, "Forbidden"));
```

Whatever the client asked for, after resolution the file either lives inside
the document root or it does not. There is nothing to outsmart.

The same principle applies to the contact form: every field is decoded, then
stripped of control characters and truncated, because a newline inside a name
would otherwise write a second, forged line into the log.

## Running it

```
make          # build
make run      # build and serve www/ on :8080
make test     # build and run the checks below
make re       # rebuild from scratch
```

`./webserv [port] [docroot]` — defaults to `8080` and `www`.

Requests are logged to stdout as they are served:

```
webserv listening on http://localhost:8080   docroot: www
GET  /                            200
GET  /style.css                   200
POST /contact                     200
   -> contact: Jane Doe <jane@example.com>
```

## Tests

`make test` boots the server on a spare port and checks the behaviour that
matters — including the attacks:

```
serving files            index, stylesheet, favicon, HEAD, 404
directory indexes        /projects/ and all five project pages
media                    photos and video served byte-for-byte intact
content types            html, css, jpeg, png, svg and mp4 all correct
HEAD                     headers sent, body withheld
docroot containment      ../ , deep ../ , and %2e%2e all refused with 403
methods                  DELETE and PUT get 501, POST to a bad path gets 404
contact form             accepted, written to disk, missing fields rejected
log injection            a newline in a field cannot forge a second log entry
keep-alive               two requests over one connection
```

All checks pass, and the server has been run through 200 concurrent requests
without a dropped connection.

## What it deliberately does not do

Knowing where to stop is part of the design:

- **TLS.** Writing your own cryptography is how you get a false sense of
  security. In production this sits behind a proxy that terminates HTTPS.
- **CGI, PHP, dynamic pages.** It serves files and handles one form. That is
  the scope.
- **Chunked transfer encoding**, `Range` requests, compression, virtual hosts.
- **A database.** The contact form appends to a file, which is the right amount
  of machinery for a contact form.

## The site

`www/` is a freelance portfolio, and it is a real one rather than a placeholder:

```
www/
├── index.html                 services, about, skills, contact
├── projects/
│   ├── index.html             all seven projects
│   ├── rage-attack/           Unity game, with gameplay video
│   ├── kitchen-rush/          Unity game, with gameplay video
│   ├── java-tower-defense/    Java, no engine
│   ├── belgelendirme/         PHP, no framework
│   ├── django-portfolio/      Python, the other portfolio site
│   ├── laptop-anatomy/        Three.js, the scroll-driven teardown
│   └── webserv/               this server
├── lab/laptop/                the 3D scene itself
├── js/                        three.js, vendored
├── tr/                        the whole site again, in Turkish
├── site.js                    keyboard control for the videos
├── img/                       project stills and screenshots
└── media/                     two MP4 gameplay clips
```

Hand-written HTML and CSS, one small `fetch()` for the contact form, no build
step and no JavaScript framework — which is precisely why a server that only
knows how to hand over files is enough to run all of it, video included.

Both languages are real pages rather than a client-side string swap: `/` is
English, `/tr/` is Turkish, and the toggle in the header is a plain link
between the two. Static files, so the server needs to know nothing about it.

`/lab/laptop/` is a WebGL teardown of a laptop driven entirely by scroll
position — eight stages, every part generated from primitives in code rather
than loaded from a model file, lit by a studio environment baked from emissive
panels and finished with soft shadows, bloom and ACES tone mapping.

Three.js and its addons are vendored under `js/` rather than pulled from a CDN,
so the page makes zero third-party requests and this binary really does serve
every byte of it. Tests assert that, that every addon in the tree resolves —
they import each other by relative path, so one missing sibling takes the whole
scene down — and that the modules go out with a JavaScript content type. A
module served as `text/plain` is a module the browser refuses to run.

The gameplay videos take the keyboard controls people already know from
YouTube — <kbd>←</kbd>/<kbd>→</kbd> to seek five seconds, <kbd>J</kbd>/<kbd>L</kbd>
for ten, <kbd>space</kbd> to play, <kbd>F</kbd> for fullscreen, <kbd>0</kbd>–<kbd>9</kbd>
to jump — and only while the pointer is on the player, so the arrow keys still
belong to the page everywhere else.

---

**Uğur Polat** — Computer Engineer, İstanbul
[ugurplt8@gmail.com](mailto:ugurplt8@gmail.com)
