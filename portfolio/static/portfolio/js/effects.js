(function () {
  "use strict";

  // ---- Scroll reveal ----
  var revealEls = document.querySelectorAll(".reveal");
  if (!("IntersectionObserver" in window)) {
    revealEls.forEach(function (el) { el.classList.add("in"); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("in");
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    revealEls.forEach(function (el) { io.observe(el); });
  }

  // ---- Card tilt on hover ----
  // Touch devices fire a stray mousemove on tap but never mouseleave, so the
  // card would stay stuck mid-tilt. Only wire this up for real pointers.
  var canHover = !window.matchMedia || window.matchMedia("(hover: hover)").matches;
  if (canHover) document.querySelectorAll(".tilt").forEach(function (card) {
    card.addEventListener("mousemove", function (e) {
      var r = card.getBoundingClientRect();
      var px = (e.clientX - r.left) / r.width - 0.5;
      var py = (e.clientY - r.top) / r.height - 0.5;
      card.style.transform =
        "perspective(700px) rotateX(" + (py * -8).toFixed(2) + "deg) rotateY(" +
        (px * 8).toFixed(2) + "deg) translateY(-4px)";
    });
    card.addEventListener("mouseleave", function () {
      card.style.transform = "";
    });
  });

  // ---- Flowing particle network background ----
  // ponytail: O(n^2) neighbour scan, fine up to a few hundred dots; switch to a
  // spatial grid if the dot count ever needs to grow much past that.
  var canvas = document.getElementById("bg-canvas");
  if (!canvas || !canvas.getContext) return;
  var ctx = canvas.getContext("2d");
  var dots = [];
  var w, h;
  var accentColor = getComputedStyle(document.body).getPropertyValue("--accent").trim() || "#6c63ff";

  function resize() {
    w = canvas.width = window.innerWidth;
    h = canvas.height = window.innerHeight;
    var count = Math.min(90, Math.round((w * h) / 22000));
    dots = [];
    for (var i = 0; i < count; i++) {
      dots.push({
        x: Math.random() * w,
        y: Math.random() * h,
        vx: (Math.random() - 0.5) * 0.35,
        vy: (Math.random() - 0.5) * 0.35,
      });
    }
  }

  function step() {
    ctx.clearRect(0, 0, w, h);
    for (var i = 0; i < dots.length; i++) {
      var d = dots[i];
      d.x += d.vx;
      d.y += d.vy;
      if (d.x < 0 || d.x > w) d.vx *= -1;
      if (d.y < 0 || d.y > h) d.vy *= -1;

      ctx.beginPath();
      ctx.arc(d.x, d.y, 1.6, 0, Math.PI * 2);
      ctx.fillStyle = accentColor;
      ctx.globalAlpha = 0.7;
      ctx.fill();

      for (var j = i + 1; j < dots.length; j++) {
        var o = dots[j];
        var dx = d.x - o.x, dy = d.y - o.y;
        var dist = Math.sqrt(dx * dx + dy * dy);
        if (dist < 130) {
          ctx.globalAlpha = (1 - dist / 130) * 0.25;
          ctx.strokeStyle = "#ffffff";
          ctx.lineWidth = 1;
          ctx.beginPath();
          ctx.moveTo(d.x, d.y);
          ctx.lineTo(o.x, o.y);
          ctx.stroke();
        }
      }
    }
    ctx.globalAlpha = 1;
    requestAnimationFrame(step);
  }

  window.addEventListener("resize", resize);
  resize();
  requestAnimationFrame(step);
})();
