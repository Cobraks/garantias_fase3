(() => {
  class Stepper {
    constructor(container) {
      this.container = container;
      this.steps = Array.from(container.querySelectorAll('[data-step]'));
      this.triggers = Array.from(container.querySelectorAll('[data-step-trigger]'));
      this.connectors = Array.from(container.querySelectorAll('.tabs__connector .connector'));
      this.currentIndex = 0;
      this.maxVisitedIndex = 0;
      this.changeCallback = null;

      this.triggers.forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
          event.preventDefault();
          const index = Number(trigger.dataset.stepTrigger);
          if (!Number.isNaN(index) && index <= this.maxVisitedIndex + 1) {
            this.goTo(index);
          }
        });
      });

      this.update();
    }

    onChange(callback) {
      this.changeCallback = callback;
      this.emit();
    }

    getCurrentStep() {
      return this.steps[this.currentIndex] ?? null;
    }

    isFirst() {
      return this.currentIndex === 0;
    }

    isLast() {
      return this.currentIndex === this.steps.length - 1;
    }

    next() {
      if (!this.isLast()) {
        this.goTo(this.currentIndex + 1);
      }
    }

    prev() {
      if (!this.isFirst()) {
        this.goTo(this.currentIndex - 1);
      }
    }

    goTo(index) {
      if (index < 0 || index >= this.steps.length || index === this.currentIndex) {
        return;
      }

      this.currentIndex = index;
      this.maxVisitedIndex = Math.max(this.maxVisitedIndex, index);
      this.update();
      this.emit();
    }

    update() {
      this.steps.forEach((step, position) => {
        const isActive = position === this.currentIndex;
        step.classList.toggle('is-active', isActive);
        step.toggleAttribute('hidden', !isActive);
        step.dataset.stepCurrent = String(isActive);
        step.setAttribute('aria-hidden', String(!isActive));
      });

      this.triggers.forEach((trigger, position) => {
        const isActive = position === this.currentIndex;
        trigger.classList.toggle('is-active', isActive);
        trigger.classList.toggle('is-completed', position < this.currentIndex);
        trigger.setAttribute('aria-current', isActive ? 'step' : 'false');
      });

      this.connectors.forEach((connector, position) => {
        connector.classList.toggle('is-active', position < this.currentIndex);
      });
    }

    emit() {
      if (typeof this.changeCallback === 'function') {
        this.changeCallback({
          index: this.currentIndex,
          isFirst: this.isFirst(),
          isLast: this.isLast(),
          total: this.steps.length,
        });
      }
    }
  }

  class ChannelFields {
    constructor(form) {
      this.form = form;
      this.select = form.querySelector('[data-channel-select]');
      this.groups = Array.from(form.querySelectorAll('[data-channel-field]'));

      if (this.select) {
        this.select.addEventListener('change', () => this.update());
      }
    }

    update() {
      const channel = this.select?.value ?? '';

      this.groups.forEach((group) => {
        const allowed = (group.dataset.channelField || '')
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean);

        const shouldDisplay = allowed.length === 0 || allowed.includes(channel);
        group.classList.toggle('is-visible', shouldDisplay);
        group.toggleAttribute('hidden', !shouldDisplay);

        const inputs = Array.from(group.querySelectorAll('input, select, textarea'));
        inputs.forEach((input) => {
          if (shouldDisplay) {
            input.disabled = false;
            if (group.dataset.channelRequired === 'true') {
              input.required = true;
            }
          } else {
            if (input.type === 'checkbox' || input.type === 'radio') {
              input.checked = false;
            } else if (input.tagName === 'SELECT') {
              input.selectedIndex = 0;
            } else if (input.type !== 'file') {
              input.value = '';
            }
            input.disabled = true;
          }
        });
      });
    }
  }

  class PasswordToggle {
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

  const isFieldVisible = (field) => {
    if (!field || field.type === 'hidden' || field.disabled) {
      return false;
    }

    if (field.closest('[hidden]')) {
      return false;
    }

    return true;
  };

  const setupMatchingFields = (form, pairs) => {
    const validators = pairs
      .map(({ primary, confirm, message }) => {
        const primaryField = form.querySelector(`#${primary}`);
        const confirmField = form.querySelector(`#${confirm}`);

        if (!primaryField || !confirmField) {
          return null;
        }

        const validate = () => {
          if (!isFieldVisible(confirmField)) {
            confirmField.setCustomValidity('');
            return;
          }

          const primaryValue = primaryField.value.trim();
          const confirmValue = confirmField.value.trim();

          if (confirmValue && primaryValue && primaryValue !== confirmValue) {
            confirmField.setCustomValidity(message);
          } else {
            confirmField.setCustomValidity('');
          }
        };

        primaryField.addEventListener('input', validate);
        confirmField.addEventListener('input', validate);
        form.addEventListener('change', (event) => {
          if (event.target === primaryField || event.target === confirmField) {
            validate();
          }
        });

        return validate;
      })
      .filter(Boolean);

    return () => {
      validators.forEach((validate) => validate());
    };
  };

  const validateStep = (step) => {
    if (!step) {
      return true;
    }

    const fields = Array.from(step.querySelectorAll('input, select, textarea')).filter(isFieldVisible);
    for (const field of fields) {
      if (!field.checkValidity()) {
        field.reportValidity();
        field.focus();
        return false;
      }
    }

    return true;
  };

  const setupRegisterForm = () => {
    const container = document.querySelector('.register-page__card');
    const form = document.querySelector('#register-form');

    if (!container || !form) {
      return;
    }

    const statusBox = container.querySelector('[data-register-status]');
    const prevButton = container.querySelector('[data-step-prev]');
    const nextButton = container.querySelector('[data-step-next]');
    const nextLabel = container.querySelector('[data-step-next-label]');

    const stepper = new Stepper(container);
    const channelFields = new ChannelFields(form);
    new PasswordToggle(form);

    const runMatchingChecks = setupMatchingFields(form, [
      {
        primary: 'register_email',
        confirm: 'register_email_confirm',
        message: 'Los correos electrónicos no coinciden.',
      },
      {
        primary: 'register_password',
        confirm: 'register_password_confirm',
        message: 'Las contraseñas no coinciden.',
      },
    ]);

    runMatchingChecks();

    const defaultNextLabel = nextLabel ? nextLabel.textContent.trim() : '';
    const finalLabel = nextLabel?.dataset.finalLabel || 'Crear cuenta';

    const setStatus = (message = '', type = 'info') => {
      if (!statusBox) {
        return;
      }

      statusBox.textContent = message;
      statusBox.dataset.statusType = message ? type : '';
    };

    if (prevButton) {
      prevButton.addEventListener('click', () => {
        stepper.prev();
        setStatus('');
      });
    }

    if (nextButton) {
      nextButton.addEventListener('click', () => {
        const currentStep = stepper.getCurrentStep();
        runMatchingChecks();
        if (!validateStep(currentStep)) {
          return;
        }

        if (stepper.isLast()) {
          form.requestSubmit();
          return;
        }

        stepper.next();
        setStatus('');
      });
    }

    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const currentStep = stepper.getCurrentStep();
      runMatchingChecks();
      if (!validateStep(currentStep)) {
        return;
      }

      setStatus(
        'Recibimos tu solicitud de registro. En breve conectaremos este flujo con el servicio de alta definitivo.',
        'success',
      );
    });

    stepper.onChange((state) => {
      if (prevButton) {
        prevButton.disabled = state.isFirst;
      }

      if (nextButton) {
        nextButton.dataset.stepLast = state.isLast ? 'true' : 'false';
      }

      if (nextLabel) {
        nextLabel.textContent = state.isLast ? finalLabel : defaultNextLabel;
      }
    });

    channelFields.update();
  };

  document.addEventListener('DOMContentLoaded', setupRegisterForm);
})();
