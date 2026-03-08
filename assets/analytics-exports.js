document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.export-btn').forEach(function(el){
    el.addEventListener('click', function(e){
      // allow ctrl/cmd click to open in new tab
      if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
      e.preventDefault();
      el.classList.add('loading');
      // small delay to show spinner before download starts
      setTimeout(function(){ window.location.href = el.getAttribute('href'); }, 150);
    });
  });
});
