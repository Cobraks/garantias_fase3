(() => {
  class Stepper {
    constructor(container) {
      this.container = container;
      this.steps = Array.from(container.querySelectorAll('[data-step]'));
      this.triggers = Array.from(container.querySelectorAll('[data-step-trigger]'));
      this.progress = container.querySelector('[data-tabs-progress]');
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
        step.classList.toggle('active', isActive);
        step.toggleAttribute('hidden', !isActive);
        step.dataset.stepCurrent = String(isActive);
        step.setAttribute('aria-hidden', String(!isActive));
      });

      this.triggers.forEach((trigger, position) => {
        const isActive = position === this.currentIndex;
        trigger.classList.toggle('is-active', isActive);
        trigger.classList.toggle('active', isActive);
        trigger.classList.toggle('is-completed', position < this.currentIndex);
        trigger.classList.toggle('completed', position < this.currentIndex);
        trigger.setAttribute('aria-current', isActive ? 'step' : 'false');
      });

      this.updateProgress();
    }

    updateProgress() {
      if (!this.progress) {
        return;
      }

      const total = this.steps.length;
      const ratio = total > 1 ? this.currentIndex / (total - 1) : 0;
      this.progress.style.setProperty('--progress-ratio', String(ratio));
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
      this.select = form.querySelector('[data-channel-select]');
      this.groups = Array.from(form.querySelectorAll('[data-channel-field]'));

      if (this.select) {
        this.select.addEventListener('change', () => this.update());
      }
    }

    update() {
      const selected = this.select?.value ?? '';

      this.groups.forEach((group) => {
        const allowed = (group.dataset.channelField || '')
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean);

        const shouldShow = allowed.length === 0 || allowed.includes(selected);
        group.classList.toggle('is-visible', shouldShow);
        group.toggleAttribute('hidden', !shouldShow);

        const required = group.dataset.channelRequired === 'true';
        const fields = Array.from(group.querySelectorAll('input, select, textarea'));

        fields.forEach((field) => {
          if (shouldShow) {
            field.disabled = false;
            if (required) {
              field.required = true;
            }
            return;
          }

          if (field.type === 'checkbox' || field.type === 'radio') {
            field.checked = false;
          } else if (field.tagName === 'SELECT') {
            field.selectedIndex = 0;
          } else if (field.type === 'file') {
            field.value = '';
          } else {
            field.value = '';
          }

          field.disabled = true;

          if (required) {
            field.required = false;
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

    const defaultNextLabel = nextLabel ? nextLabel.textContent.trim() : '';
    const finalLabel = nextLabel?.dataset.finalLabel || 'Registrar';

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
      setStatus(
        'Hemos recibido tu solicitud. Nuestro equipo revisará los datos y activará tu cuenta en cuanto sea posible.',
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
