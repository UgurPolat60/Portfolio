#!/usr/bin/env bash
# The smallest thing that fails if the server breaks.
# Boots webserv on a spare port, throws the requests that matter at it,
# and exits non-zero if any answer is wrong.  Run it with: make test

set -u
PORT=${PORT:-8099}
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT" || exit 1

[ -x ./webserv ] || { echo "build first: make"; exit 1; }

rm -f contact.log
./webserv "$PORT" www >/dev/null 2>&1 &
SRV=$!
trap 'kill $SRV 2>/dev/null' EXIT

# wait for the socket to come up instead of guessing with sleep
for _ in $(seq 60); do
	curl -fsS -o /dev/null "http://127.0.0.1:$PORT/" 2>/dev/null && break
	sleep 0.05
done

fail=0
check() {
	if [ "$2" = "$3" ]; then
		printf '  ok    %-34s %s\n' "$1" "$3"
	else
		printf '  FAIL  %-34s expected %s, got %s\n' "$1" "$2" "$3"
		fail=1
	fi
}
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

U="http://127.0.0.1:$PORT"

echo "serving files"
check "GET /"                 200 "$(code "$U/")"
check "GET /style.css"        200 "$(code "$U/style.css")"
check "GET /favicon.svg"      200 "$(code "$U/favicon.svg")"
check "HEAD /"                200 "$(code -I "$U/")"
check "GET /missing.html"     404 "$(code "$U/missing.html")"

echo "nested pages get their directory index"
check "GET /projects/"        200 "$(code "$U/projects/")"
check "GET a project page"    200 "$(code "$U/projects/rage-attack/")"
check "GET every project"     "200200200200200200200" \
	"$(curl -s -o /dev/null -w '%{http_code}' "$U/projects/rage-attack/" \
		-o /dev/null "$U/projects/kitchen-rush/" \
		-o /dev/null "$U/projects/java-tower-defense/" \
		-o /dev/null "$U/projects/belgelendirme/" \
		-o /dev/null "$U/projects/django-portfolio/" \
		-o /dev/null "$U/projects/laptop-anatomy/" \
		-o /dev/null "$U/projects/webserv/")"

echo "the 3D scene and its dependencies"
check "GET /lab/laptop/"      200 "$(code "$U/lab/laptop/")"
check "GET /tr/lab/laptop/"   200 "$(code "$U/tr/lab/laptop/")"
check "scene script"          200 "$(code "$U/lab/laptop/laptop.js")"
check "three.js module"       200 "$(code "$U/js/three.module.min.js")"
check "three.js core"         200 "$(code "$U/js/three.core.min.js")"
# The addon tree has to keep its shape: these files import each other by
# relative path, so one missing sibling takes the whole scene down.
addons="geometries/RoundedBoxGeometry.js postprocessing/EffectComposer.js
	postprocessing/RenderPass.js postprocessing/ShaderPass.js postprocessing/MaskPass.js
	postprocessing/Pass.js postprocessing/UnrealBloomPass.js postprocessing/OutputPass.js
	shaders/CopyShader.js shaders/LuminosityHighPassShader.js shaders/OutputShader.js"
missing=0
for a in $addons; do
	[ "$(code "$U/js/three/addons/$a")" = "200" ] || { echo "     missing: $a"; missing=$((missing + 1)); }
done
check "every three.js addon"  0 "$missing"
check "three.js is intact"    "$(wc -c < www/js/three.core.min.js | tr -d ' ')" \
	"$(curl -s -o /dev/null -w '%{size_download}' "$U/js/three.core.min.js")"
# a module served as text/plain is a module the browser refuses to run
check "module content-type"   "application/javascript; charset=utf-8" \
	"$(curl -sI "$U/js/three.module.min.js" | tr -d '\r' | awk -F': ' '/^Content-Type/{print $2}')"
check "no CDN in the page"    0 \
	"$(grep -c 'https\?://\(unpkg\|cdn\|cdnjs\|jsdelivr\)' www/lab/laptop/index.html)"

echo "the Turkish mirror"
check "GET /tr/"              200 "$(code "$U/tr/")"
check "GET /tr/projects/"     200 "$(code "$U/tr/projects/")"
check "every Turkish project" "200200200200200200200" \
	"$(curl -s -o /dev/null -w '%{http_code}' "$U/tr/projects/rage-attack/" \
		-o /dev/null "$U/tr/projects/kitchen-rush/" \
		-o /dev/null "$U/tr/projects/java-tower-defense/" \
		-o /dev/null "$U/tr/projects/belgelendirme/" \
		-o /dev/null "$U/tr/projects/django-portfolio/" \
		-o /dev/null "$U/tr/projects/laptop-anatomy/" \
		-o /dev/null "$U/tr/projects/webserv/")"
check "Turkish page is Turkish" 1 \
	"$(curl -s "$U/tr/" | grep -c 'lang="tr"')"
check "UTF-8 survives the wire" yes \
	"$(curl -s "$U/tr/" | grep -q 'Bilgisayar Mühendisliği' && echo yes || echo no)"

echo "media"
check "GET a photo"           200 "$(code "$U/img/rage-overview.jpg")"
check "GET the portrait"      200 "$(code "$U/img/ugur.jpg")"
check "GET site.js"           200 "$(code "$U/site.js")"
check "GET a video"           200 "$(code "$U/media/kitchen-rush.mp4")"
check "video is intact"       "$(wc -c < www/media/kitchen-rush.mp4 | tr -d ' ')" \
	"$(curl -s -o /dev/null -w '%{size_download}' "$U/media/kitchen-rush.mp4")"

echo "seeking inside a video"
# without these a <video> cannot jump: the browser has no way to ask for the
# middle of the file, so every seek silently restarts playback at zero
V="$U/media/kitchen-rush.mp4"
SIZE=$(wc -c < www/media/kitchen-rush.mp4 | tr -d ' ')
crange() { curl -sI -H "Range: $1" "$V" | tr -d '\r' | awk -F': ' '/^Content-Range/{print $2}'; }
check "a slice from the middle" 206 "$(code -H 'Range: bytes=100000-100099' "$V")"
check "and it says which slice" "bytes 100000-100099/$SIZE" "$(crange 'bytes=100000-100099')"
check "open-ended range"        206 "$(code -H 'Range: bytes=100000-' "$V")"
check "last 500 bytes"          "bytes $((SIZE - 500))-$((SIZE - 1))/$SIZE" "$(crange 'bytes=-500')"
check "past the end"            416 "$(code -H 'Range: bytes=99999999-' "$V")"
check "nonsense range ignored"  200 "$(code -H 'Range: kilobytes=1-2' "$V")"
check "the bytes are the right bytes" \
	"$(tail -c +100001 www/media/kitchen-rush.mp4 | head -c 100 | md5sum | cut -d' ' -f1)" \
	"$(curl -s -H 'Range: bytes=100000-100099' "$V" | md5sum | cut -d' ' -f1)"
check "seeking is advertised"   "bytes" \
	"$(curl -sI "$V" | tr -d '\r' | awk -F': ' '/^Accept-Ranges/{print $2}')"

echo "content types"
ctype() { curl -sI "$1" | tr -d '\r' | awk -F': ' '/^Content-Type/{print $2}'; }
check "html content-type"     "text/html; charset=utf-8" "$(ctype "$U/")"
check "css content-type"      "text/css; charset=utf-8"  "$(ctype "$U/style.css")"
check "jpeg content-type"     "image/jpeg"               "$(ctype "$U/img/rage-overview.jpg")"
check "png content-type"      "image/png"                "$(ctype "$U/img/java-editor.png")"
check "svg content-type"      "image/svg+xml"            "$(ctype "$U/favicon.svg")"
check "mp4 content-type"      "video/mp4"                "$(ctype "$U/media/kitchen-rush.mp4")"

echo "HEAD sends headers but no body"
check "HEAD has no body"      0 "$(curl -s --head "$U/" -o /dev/null -w '%{size_download}')"

echo "refusing to leave the docroot"
check "../ escape"            403 "$(code --path-as-is "$U/../Makefile")"
# enough ".." to hit / from any docroot, so this really does resolve to a
# real file outside the tree rather than to something that merely 404s
DEEP=$(printf '../%.0s' $(seq 20))
check "deep ../ escape"       403 "$(code --path-as-is "$U/$DEEP""etc/passwd")"
check "percent-encoded ../"   403 "$(code --path-as-is "$U/%2e%2e/Makefile")"

echo "methods"
check "DELETE"                501 "$(code -X DELETE "$U/")"
check "PUT"                   501 "$(code -X PUT "$U/")"
check "POST to unknown path"  404 "$(code -X POST -d 'a=1' "$U/nope")"

echo "the contact form"
check "POST /contact"         200 \
	"$(code -X POST -d 'name=Test User&email=test@example.com&message=hello there' "$U/contact")"
check "POST missing fields"   400 "$(code -X POST -d 'name=only' "$U/contact")"
if grep -q 'test@example.com' contact.log 2>/dev/null; then
	printf '  ok    %-34s %s\n' "message written to contact.log" "yes"
else
	printf '  FAIL  %-34s %s\n' "message written to contact.log" "missing"
	fail=1
fi
# a newline inside a field must not be able to forge a second log entry:
# two accepted messages must leave exactly two lines behind, never three
curl -s -o /dev/null -X POST --data-urlencode 'name=A
[2000-01-01 00:00:00] forged <x>: x' --data 'email=e@x.co' --data 'message=m' "$U/contact"
check "log injection blocked" 2 "$(wc -l < contact.log | tr -d ' ')"

echo "keep-alive"
check "two requests, one socket" "200 200" \
	"$(curl -s -o /dev/null -o /dev/null -w '%{http_code} ' "$U/" "$U/style.css" | sed 's/ $//')"

echo
if [ "$fail" = 0 ]; then
	echo "all checks passed"
else
	echo "FAILURES"
fi
exit "$fail"
