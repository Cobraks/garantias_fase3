(() => {
  class Stepper {
    constructor(container) {
      this.container = container;
      this.steps = Array.from(container.querySelectorAll('[data-step]'));
      this.triggers = Array.from(container.querySelectorAll('[data-step-trigger]'));
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
      this.channelSelect = form.querySelector('[data-channel-select]');
      this.stepIntro = form.querySelector('[data-channel-empty]');
      this.sections = Array.from(form.querySelectorAll('[data-channel-section]'));
      this.toggleControls = Array.from(form.querySelectorAll('[data-toggle-control]'));
      this.channelElements = Array.from(form.querySelectorAll('[data-channel-visible]'));

      if (this.channelSelect) {
        this.channelSelect.addEventListener('change', () => this.handleChannelChange());
      }

      this.toggleControls.forEach((control) => {
        control.addEventListener('change', () => this.handleToggle(control));
      });

      this.handleChannelChange();
    }

    getSelectedChannel() {
      return this.channelSelect?.value ?? '';
    }

    handleChannelChange() {
      const channel = this.getSelectedChannel();
      const hasChannel = Boolean(channel);

      if (this.stepIntro) {
        this.stepIntro.classList.toggle('is-hidden', hasChannel);
      }

      this.sections.forEach((section) => {
        const allowedChannels = (section.dataset.channelSection || '')
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean);
        const shouldDisplay = allowedChannels.length === 0 || allowedChannels.includes(channel);
        const isVisible = shouldDisplay && hasChannel;
        section.classList.toggle('is-active', isVisible);
        section.toggleAttribute('hidden', !isVisible);
      });

      this.channelElements.forEach((element) => {
        const allowedChannels = (element.dataset.channelVisible || '')
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean);
        const shouldDisplay = allowedChannels.length === 0 || allowedChannels.includes(channel);
        const isVisible = shouldDisplay && hasChannel;

        element.classList.toggle('is-active', isVisible);
        element.toggleAttribute('hidden', !isVisible);

        const inputs = Array.from(element.querySelectorAll('input, select, textarea'));
        inputs.forEach((input) => {
          if (!isVisible) {
            if (input.type === 'checkbox' || input.type === 'radio') {
              input.checked = false;
            } else if (input.tagName === 'SELECT') {
              input.selectedIndex = 0;
            } else if (input.type !== 'file') {
              input.value = '';
            }
          }

          if (input.dataset.preserveDisabled !== 'true') {
            input.disabled = !isVisible;
          }
        });
      });

      this.toggleControls.forEach((control) => {
        const allowedChannel = control.dataset.channelOnly;
        const restrictsChannel = Boolean(allowedChannel);
        const isAllowed = !restrictsChannel || allowedChannel === channel;

        control.disabled = !isAllowed;

        if (!isAllowed) {
          if (control.type === 'checkbox') {
            control.checked = false;
          } else {
            control.value = '';
          }
        }

        this.handleToggle(control);
      });
    }

    handleToggle(control) {
      const targetGroup = control.dataset.toggleControl;
      if (!targetGroup) {
        return;
      }

      const group = this.form.querySelector(`[data-toggle-group="${targetGroup}"]`);
      if (!group) {
        return;
      }

      const isActive = control.type === 'checkbox'
        ? control.checked
        : control.value.trim().length > 0;

      group.classList.toggle('is-active', isActive && !control.disabled);
      group.toggleAttribute('hidden', !(isActive && !control.disabled));

      const inputs = Array.from(group.querySelectorAll('input, select, textarea'));
      inputs.forEach((input) => {
        if (control.dataset.toggleRequired === 'true') {
          input.required = isActive && !control.disabled;
        }

        if (!isActive || control.disabled) {
          if (input.type === 'checkbox' || input.type === 'radio') {
            input.checked = false;
          } else if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
          } else if (input.type !== 'file') {
            input.value = '';
          }
        }
      });
    }
  }

  class Summary {
    constructor(form) {
      this.form = form;
      this.summaryMap = new Map();

      form.querySelectorAll('[data-summary-field]').forEach((node) => {
        this.summaryMap.set(node.dataset.summaryField, node);
      });

      this.handleInput = this.handleInput.bind(this);
      form.addEventListener('input', this.handleInput);
      form.addEventListener('change', this.handleInput);

      this.refresh();
    }

    handleInput(event) {
      const field = event.target;
      this.updateField(field.id, field);
    }

    refresh() {
      this.summaryMap.forEach((_, fieldId) => {
        const field = this.form.querySelector(`#${fieldId}`);
        this.updateField(fieldId, field);
      });
    }

    updateField(fieldId, field) {
      const target = this.summaryMap.get(fieldId);
      if (!target) {
        return;
      }

      let value = '';
      if (field) {
        if (field.tagName === 'SELECT') {
          const option = field.options[field.selectedIndex];
          value = option ? option.textContent.trim() : '';
        } else if (field.type === 'checkbox') {
          value = field.checked ? (field.dataset.summaryOn || field.value || '✔') : '';
        } else if (field.type === 'file') {
          value = field.files && field.files.length > 0 ? field.files[0].name : '';
        } else {
          value = field.value.trim();
        }
      }

      target.textContent = value;
      target.dataset.summaryEmpty = value ? 'false' : 'true';
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

    const conditional = field.closest('.register-conditional');
    if (conditional && !conditional.classList.contains('is-active')) {
      return false;
    }

    if (field.closest('[hidden]')) {
      return false;
    }

    return true;
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
    const summary = new Summary(form);
    new PasswordToggle(form);

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
      if (!validateStep(currentStep)) {
        return;
      }

      summary.refresh();
      setStatus(
        'Recibimos tu solicitud de registro. Próximamente conectaremos este flujo con el servicio de alta.',
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

      if (state.isLast) {
        summary.refresh();
      }
    });

    channelFields.handleChannelChange();
  };

  document.addEventListener('DOMContentLoaded', setupRegisterForm);
})();
