window.scrollToFirst = function(selector){
  const el = document.querySelector(selector);
  if (el) el.scrollIntoView({behavior:'smooth', block:'start'});
};
