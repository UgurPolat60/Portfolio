(function () {
  "use strict";

  document.querySelectorAll("[data-showcase]").forEach(function (root) {
    var stage = root.querySelector(".showcase__stage");
    var ring = root.querySelector(".showcase__ring");
    var faces = root.querySelectorAll(".showcase__face");
    var dots = root.querySelectorAll(".showcase__dots button");
    var count = faces.length;
    if (!count || !ring) return;

    var step = 360 / count;

    // The ring is sized in vw, so the radius has to be recomputed whenever the
    // viewport changes — otherwise a phone rotation leaves the faces detached.
    function setDepth() {
      // ring.offsetWidth, not a face rect: the faces are transformed and sit
      // under a perspective, so their measured width shrinks on every re-read.
      var faceWidth = ring.offsetWidth || 300;
      ring.style.setProperty("--depth", Math.round((faceWidth / 2) / Math.tan(Math.PI / count)) + "px");
    }
    setDepth();
    window.addEventListener("resize", setDepth);

    var rotation = 0;
    var current = 0;
    var dragging = false;
    var startX = 0;
    var startRotation = 0;
    var autoTimer = null;

    function setRotation(r, withTransition) {
      rotation = r;
      ring.classList.toggle("is-dragging", !withTransition);
      ring.style.setProperty("--rot", rotation + "deg");
    }

    function goTo(index) {
      current = ((index % count) + count) % count;
      setRotation(-current * step, true);
      dots.forEach(function (d, i) { d.classList.toggle("is-active", i === current); });
    }

    function stopAuto() {
      if (autoTimer) clearInterval(autoTimer);
      autoTimer = null;
    }
    function startAuto() {
      if (count < 2) return;
      stopAuto();
      autoTimer = setInterval(function () { goTo(current + 1); }, 3200);
    }

    dots.forEach(function (dot, i) {
      dot.addEventListener("click", function () {
        stopAuto();
        goTo(i);
        startAuto();
      });
    });

    function pointerX(e) {
      return e.touches ? e.touches[0].clientX : e.clientX;
    }
    function pointerDown(e) {
      if (count < 2) return;
      dragging = true;
      startX = pointerX(e);
      startRotation = rotation;
      stopAuto();
      setRotation(rotation, false);
    }
    function pointerMove(e) {
      if (!dragging) return;
      var delta = pointerX(e) - startX;
      setRotation(startRotation + delta * 0.4, false);
    }
    function pointerUp() {
      if (!dragging) return;
      dragging = false;
      goTo(Math.round(-rotation / step));
      startAuto();
    }

    stage.addEventListener("mousedown", pointerDown);
    window.addEventListener("mousemove", pointerMove);
    window.addEventListener("mouseup", pointerUp);
    stage.addEventListener("touchstart", pointerDown, { passive: true });
    window.addEventListener("touchmove", pointerMove, { passive: true });
    window.addEventListener("touchend", pointerUp);

    goTo(0);
    startAuto();
  });
})();
