// masonry_news.js - lightweight: setzt column-count basierend auf data-attributes
document.addEventListener('DOMContentLoaded', function() {
  var wrappers = document.querySelectorAll('.masonry-wrapper');
  wrappers.forEach(function(w) {
    var grid = w.querySelector('.masonry-grid');
    if (!grid) return;

    var desktop = parseInt(w.dataset.columnsDesktop) || 3;
    var tablet  = parseInt(w.dataset.columnsTablet) || 2;
    var mobile  = parseInt(w.dataset.columnsMobile) || 1;

    function applyColumns() {
      var wWidth = window.innerWidth;
      var cols = desktop;
      if (wWidth <= 767) cols = mobile;
      else if (wWidth <= 1199) cols = tablet;
      grid.style.columnCount = cols;
    }

    applyColumns();
    window.addEventListener('resize', function() { applyColumns(); });
  });
});
