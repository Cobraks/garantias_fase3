document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('register-form');
  if (!form) {
    return;
  }

  const steps = Array.from(document.querySelectorAll('.form-step'));
  const tabs = Array.from(document.querySelectorAll('.tabs__link'));
  const progressBar = document.querySelector('.tabs__progress');
  const prevButtons = Array.from(form.querySelectorAll('[data-step-prev]'));
  const nextButtons = Array.from(form.querySelectorAll('[data-step-next]'));

  let currentStep = 0;

  const updateNavState = () => {
    prevButtons.forEach((button) => {
      const isDisabled = currentStep === 0;
      button.toggleAttribute('disabled', isDisabled);
      button.setAttribute('aria-disabled', String(isDisabled));
    });
  };

  const goToStep = (stepIndex) => {
    const maxIndex = steps.length - 1;
    currentStep = Math.max(0, Math.min(stepIndex, maxIndex));

    steps.forEach((step, index) => {
      step.classList.toggle('form-step--active', index === currentStep);
    });

    tabs.forEach((tab, index) => {
      tab.classList.toggle('active', index === currentStep);
      tab.classList.toggle('completed', index < currentStep);
      tab.setAttribute('aria-pressed', index === currentStep ? 'true' : 'false');
    });

    if (progressBar && maxIndex > 0) {
      const progress = (currentStep / maxIndex) * 100;
      progressBar.style.width = `${progress}%`;
    }

    updateNavState();
  };

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      const targetStep = Number(tab.dataset.step || 0);
      goToStep(targetStep);
    });
  });

  nextButtons.forEach((button) => {
    button.addEventListener('click', () => {
      goToStep(currentStep + 1);
    });
  });

  prevButtons.forEach((button) => {
    button.addEventListener('click', () => {
      goToStep(currentStep - 1);
    });
  });

  const accountType = document.getElementById('register_account_type');
  const companyBlock = document.getElementById('register_company');
  const updateCompanyVisibility = () => {
    if (!companyBlock) {
      return;
    }
    const isProfessional = accountType && accountType.value === 'professional';
    companyBlock.classList.toggle('is-visible', Boolean(isProfessional));
  };
  if (accountType) {
    accountType.addEventListener('change', updateCompanyVisibility);
  }
  updateCompanyVisibility();

  const toggleCollapsible = (checkboxId, targetId) => {
    const checkbox = document.getElementById(checkboxId);
    const target = document.getElementById(targetId);
    if (!checkbox || !target) {
      return;
    }
    const update = () => {
      target.classList.toggle('is-visible', checkbox.checked);
    };
    checkbox.addEventListener('change', update);
    update();
  };

  toggleCollapsible('has_workshop', 'workshop-fields');
  toggleCollapsible('auto_signature', 'signature-fields');
  toggleCollapsible('enable_sepa', 'sepa-fields');

  const uploadBlocks = document.querySelectorAll('.register-upload');
  uploadBlocks.forEach((block) => {
    const input = block.querySelector('.register-upload__input');
    const label = block.querySelector('.register-upload__label');
    if (!input || !label) {
      return;
    }
    if (!label.dataset.defaultLabel) {
      label.dataset.defaultLabel = label.textContent || '';
    }
    block.addEventListener('click', () => {
      input.click();
    });
    input.addEventListener('change', () => {
      const files = input.files;
      if (files && files.length > 0) {
        block.classList.add('is-filled');
        label.textContent = files[0].name;
      } else {
        block.classList.remove('is-filled');
        label.textContent = label.dataset.defaultLabel || '';
      }
    });
  });

  const passwordToggles = document.querySelectorAll('.password-toggle');
  passwordToggles.forEach((toggle) => {
    const targetId = toggle.getAttribute('data-toggle-target');
    if (!targetId) {
      return;
    }
    const input = document.getElementById(targetId);
    if (!input) {
      return;
    }
    toggle.addEventListener('click', () => {
      const shouldShow = input.type === 'password';
      input.type = shouldShow ? 'text' : 'password';
      toggle.classList.toggle('is-visible', shouldShow);
    });
  });

  const helpToggles = document.querySelectorAll('.help-toggle');
  helpToggles.forEach((toggle) => {
    const targetId = toggle.getAttribute('data-help-target');
    if (!targetId) {
      return;
    }
    const panel = document.getElementById(targetId);
    if (!panel) {
      return;
    }
    const closeButton = panel.querySelector('[data-help-close]');
    const togglePanel = () => {
      const isHidden = panel.hasAttribute('hidden');
      if (isHidden) {
        panel.removeAttribute('hidden');
        toggle.classList.add('is-active');
        toggle.setAttribute('aria-expanded', 'true');
      } else {
        panel.setAttribute('hidden', '');
        toggle.classList.remove('is-active');
        toggle.setAttribute('aria-expanded', 'false');
      }
    };
    toggle.addEventListener('click', togglePanel);
    if (closeButton) {
      closeButton.addEventListener('click', togglePanel);
    }
  });

  form.addEventListener('submit', (event) => {
    event.preventDefault();
    if (typeof form.reportValidity === 'function' && !form.reportValidity()) {
      return;
    }
    window.alert('Tu solicitud de registro se ha enviado correctamente. Nos pondremos en contacto contigo muy pronto.');
  });

  goToStep(0);
});
