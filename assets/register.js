document.addEventListener('DOMContentLoaded', function () {
  const form = document.querySelector('.auth-form-content');
  if (!form) return;

  form.addEventListener('submit', function (e) {
    const password = (form.querySelector('#password') || {}).value || '';
    if (password.length > 0 && password.length < 6) {
      e.preventDefault();
      alert('Password should be at least 6 characters long.');
    }
  });
});