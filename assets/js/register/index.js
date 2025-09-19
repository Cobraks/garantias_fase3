import Stepper from './modules/stepper.js';
import ChannelFields from './modules/channel-fields.js';
import Summary from './modules/summary.js';
import PasswordToggle from './modules/password-toggle.js';

const isFieldVisible = (field) => {
  if (!field || field.type === 'hidden' || field.disabled) {
    return false;
  }

  const conditional = field.closest('.register-conditional');
  if (conditional && !conditional.classList.contains('is-active')) {
    return false;
  }

  const channelSection = field.closest('[data-channel-section]');
  if (channelSection && !channelSection.classList.contains('is-active')) {
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
  const container = document.querySelector('.register-form__container');
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
  const passwordToggle = new PasswordToggle(form);

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
    setStatus('Recibimos tu solicitud de registro. Próximamente conectaremos este flujo con el servicio de alta.', 'success');
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

  // Inicializa dependencias dependientes del canal
  channelFields.handleChannelChange();
};

document.addEventListener('DOMContentLoaded', setupRegisterForm);
