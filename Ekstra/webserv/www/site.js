/* Keyboard control for the project videos, the way YouTube does it.
   Keys only fire while the pointer is over a video or the video has focus —
   otherwise arrows and space belong to the page, not to us. */
(function () {
	var videos = Array.prototype.slice.call(document.querySelectorAll('video'));
	if (!videos.length) return;

	videos.forEach(function (v) { v.tabIndex = 0; });

	var hint = document.createElement('div');
	hint.className = 'vhint';
	document.body.appendChild(hint);
	var timer;
	function flash(text) {
		hint.textContent = text;
		hint.classList.add('on');
		clearTimeout(timer);
		timer = setTimeout(function () { hint.classList.remove('on'); }, 800);
	}

	function target() {
		for (var i = 0; i < videos.length; i++) {
			if (videos[i].matches(':hover')) return videos[i];
		}
		var a = document.activeElement;
		return (a && a.tagName === 'VIDEO') ? a : null;
	}

	function seek(v, delta) {
		var t = v.currentTime + delta;
		v.currentTime = Math.max(0, Math.min(v.duration || 0, t));
		flash((delta > 0 ? '⏩  +' : '⏪  −') + Math.abs(delta) + 's');
	}

	document.addEventListener('keydown', function (e) {
		if (e.ctrlKey || e.altKey || e.metaKey) return;
		var v = target();
		if (!v) return;

		switch (e.key) {
			case 'ArrowRight': seek(v, 5); break;
			case 'ArrowLeft': seek(v, -5); break;
			case 'l': case 'L': seek(v, 10); break;
			case 'j': case 'J': seek(v, -10); break;
			case ' ': case 'k': case 'K':
				if (v.paused) { v.play(); flash('▶'); }
				else { v.pause(); flash('❘❘'); }
				break;
			case 'f': case 'F':
				if (document.fullscreenElement) document.exitFullscreen();
				else if (v.requestFullscreen) v.requestFullscreen();
				flash('⛶');
				break;
			case 'm': case 'M':
				v.muted = !v.muted;
				flash(v.muted ? 'muted' : 'sound on');
				break;
			case 'Home': v.currentTime = 0; flash('⏮'); break;
			case 'End': v.currentTime = v.duration || 0; flash('⏭'); break;
			default:
				if (e.key >= '0' && e.key <= '9' && v.duration) {
					v.currentTime = v.duration * (Number(e.key) / 10);
					flash(Number(e.key) * 10 + '%');
					break;
				}
				return;               /* not ours: let the page have the key */
		}
		e.preventDefault();
	});
})();
