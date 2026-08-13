(function () {
  "use strict";

  var box = document.getElementById("mood-box");
  if (!box) return;
  var stage = box.querySelector(".mood-box__stage");

  function setState(state) {
    if (box.dataset.state === state) return;
    box.dataset.state = state;
    // Restart the burst ring: drop the class, force a reflow, re-add it.
    // Without the reflow the browser coalesces both changes and never replays.
    stage.classList.remove("is-burst");
    void stage.offsetWidth;
    stage.classList.add("is-burst");
  }

  // Mouse over a project card/link -> brag. Over the CV/education/skills
  // block -> graduate. Anywhere else -> idle (smile). Nearest ancestor wins,
  // so a project card inside the CV section still bragges.
  document.addEventListener("mousemove", function (e) {
    var el = e.target.closest ? e.target.closest("[data-mood]") : null;
    setState(el ? el.dataset.mood : "idle");
  });

  // Mouse leaves the browser viewport entirely -> scared.
  // relatedTarget/toElement is null only when the pointer left the window.
  document.addEventListener("mouseout", function (e) {
    if (!e.relatedTarget && !e.toElement) {
      setState("scared");
    }
  });

  // Later: drop a <video autoplay muted loop playsinline> into each
  // .mood-face[data-face="..."] block in base.html — no JS changes needed.
})();
