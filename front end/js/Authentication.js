document.addEventListener('DOMContentLoaded', () => {
    const container = document.querySelector('.container');
    const registerBtns = document.querySelectorAll('.register-btn');
    const loginBtns = document.querySelectorAll('.login-btn');
    const body = document.body;

    const urlParams = new URLSearchParams(window.location.search);
    const show = urlParams.get('show');
    const error = urlParams.get('error');
    
    if (show === 'register' || error === '1') {
        container.classList.add('active');
        body.classList.add('theme-register');
        body.classList.remove('theme-login');
    } else {
        body.classList.add('theme-login');
    }
    
    
    const fadeBody = (themeToAdd, themeToRemove) => {
      body.classList.add('fade');
      setTimeout(() => {
        body.classList.remove(themeToRemove);
        body.classList.add(themeToAdd);
        body.classList.remove('fade');
      }, 1000); 
    };
  
    registerBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        container.classList.add('active');
        fadeBody('theme-register', 'theme-login');
        const path = window.location.pathname;
        history.replaceState(null, '', `${path}?show=register`);
      });
    });
    
    loginBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        container.classList.remove('active');
        fadeBody('theme-login', 'theme-register');
        const path = window.location.pathname;
        history.replaceState(null, '', `${path}?show=login`);
      });
    });
    

  });
  