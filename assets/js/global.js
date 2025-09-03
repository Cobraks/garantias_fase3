document.addEventListener('DOMContentLoaded',()=>{
  const burger=document.querySelector('.top-bar__hamburger');
  const mobile=document.querySelector('.mobile-menu');
  const profileBtn=document.querySelector('.top-bar__profile-link');
  const profileMenu=document.querySelector('.top-bar__profile-menu');
  burger&&burger.addEventListener('click',e=>{e.stopPropagation();mobile.classList.toggle('open');});
  profileBtn&&profileBtn.addEventListener('click',e=>{e.stopPropagation();profileMenu.classList.toggle('visible');});
  document.addEventListener('click',()=>{mobile&&mobile.classList.remove('open');profileMenu&&profileMenu.classList.remove('visible');});
  profileMenu&&profileMenu.addEventListener('click',e=>e.stopPropagation());
});
