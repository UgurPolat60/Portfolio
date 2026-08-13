# Two stages so the shipped image carries the binary, not the compiler.
FROM debian:bookworm-slim AS build
RUN apt-get update && apt-get install -y --no-install-recommends build-essential \
	&& rm -rf /var/lib/apt/lists/*
WORKDIR /src
COPY Makefile ./
COPY include ./include
COPY src ./src
RUN make

FROM debian:bookworm-slim
WORKDIR /app
COPY --from=build /src/webserv ./webserv
COPY www ./www
# Render terminates TLS and hands the port in $PORT. Everything behind that —
# the parsing, the ranges, the bytes on the wire — is still this server.
CMD ["sh", "-c", "./webserv ${PORT:-8080} www"]
