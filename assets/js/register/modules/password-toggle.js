export default class PasswordToggle {
  constructor(form) {
    this.form = form;
    this.buttons = Array.from(form.querySelectorAll('[data-password-toggle]'));
    this.buttons.forEach((button) => {
      button.addEventListener('click', () => this.toggle(button));
    });
  }

  toggle(button) {
    const fieldId = button.dataset.passwordToggle;
    if (!fieldId) {
      return;
    }

    const input = this.form.querySelector(`#${fieldId}`);
    if (!input) {
      return;
    }

    const label = button.querySelector('[data-password-toggle-label]');
    const isPassword = input.type === 'password';
    input.type = isPassword ? 'text' : 'password';

    const showText = button.dataset.showText || 'Mostrar';
    const hideText = button.dataset.hideText || 'Ocultar';

    if (label) {
      label.textContent = isPassword ? hideText : showText;
    }
  }
}
