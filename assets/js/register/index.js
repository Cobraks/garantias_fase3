(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const steps = document.querySelectorAll('.step');
    const formSteps = document.querySelectorAll('.form-step');
    const progressBar = document.getElementById('progress-bar');
    const progressContainer = document.querySelector('.progress-container');
    const nextButtons = document.querySelectorAll('[data-next-step]');
    const prevButtons = document.querySelectorAll('[data-prev-step]');
    const registerBtn = document.getElementById('register-btn');
    const verifyBtn = document.getElementById('verify-btn');

    let currentStep = 1;
    const totalSteps = 3;

    const updateProgress = () => {
      if (progressBar) {
        const progress = totalSteps > 1 ? ((currentStep - 1) / (totalSteps - 1)) * 100 : 0;
        progressBar.style.width = `${progress}%`;
      }

      steps.forEach((step) => {
        const stepNumber = Number(step.getAttribute('data-step'));
        step.classList.remove('active', 'completed');
        if (stepNumber < currentStep) {
          step.classList.add('completed');
        } else if (stepNumber === currentStep) {
          step.classList.add('active');
        }
      });
    };

    const showCurrentStep = () => {
      formSteps.forEach((step) => {
        step.classList.remove('active');
      });
      const activeStep = document.getElementById(`step-${currentStep}`);
      if (activeStep) {
        activeStep.classList.add('active');
      }
    };

    const goToStep = (stepNumber) => {
      currentStep = Math.max(1, Math.min(stepNumber, totalSteps));
      showCurrentStep();
      updateProgress();
      if (progressContainer) {
        progressContainer.style.display = '';
      }
    };

    nextButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (currentStep < totalSteps) {
          currentStep += 1;
          goToStep(currentStep);
        }
      });
    });

    prevButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (currentStep > 1) {
          currentStep -= 1;
          goToStep(currentStep);
        }
      });
    });

    if (registerBtn) {
      registerBtn.addEventListener('click', () => {
        const terms = document.getElementById('terms');
        if (terms && !terms.checked) {
          window.alert('Debes aceptar los términos y condiciones para continuar.');
          return;
        }

        const step3 = document.getElementById('step-3');
        const step4 = document.getElementById('step-4');
        if (step3) {
          step3.classList.remove('active');
        }
        if (step4) {
          step4.classList.add('active');
        }
        if (progressContainer) {
          progressContainer.style.display = 'none';
        }

        const emailSent = document.getElementById('email-sent');
        const emailField = document.getElementById('email');
        if (emailSent && emailField) {
          emailSent.textContent = emailField.value || emailSent.textContent;
        }
      });
    }

    if (verifyBtn) {
      verifyBtn.addEventListener('click', () => {
        const codeField = document.getElementById('verification_code');
        if (codeField && !codeField.value) {
          window.alert('Por favor, introduce el código de verificación.');
          return;
        }

        window.alert('¡Cuenta verificada con éxito! Ahora puedes iniciar sesión.');
      });
    }

    const channelButtons = document.querySelectorAll('.channel-btn');
    const companyField = document.getElementById('company-field');

    const updateChannel = (channel) => {
      channelButtons.forEach((button) => {
        button.classList.toggle('active', button.getAttribute('data-channel') === channel);
      });

      if (companyField) {
        if (channel === 'professional') {
          companyField.classList.add('visible');
        } else {
          companyField.classList.remove('visible');
        }
      }
    };

    if (channelButtons.length) {
      updateChannel(channelButtons[0].getAttribute('data-channel'));
      channelButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const channel = button.getAttribute('data-channel');
          updateChannel(channel);
        });
      });
    }

    const toggleVisibility = (checkboxId, fieldId) => {
      const checkbox = document.getElementById(checkboxId);
      const field = document.getElementById(fieldId);
      if (!checkbox || !field) {
        return;
      }

      const update = () => {
        field.classList.toggle('visible', checkbox.checked);
      };

      checkbox.addEventListener('change', update);
      update();
    };

    toggleVisibility('has_workshop', 'workshop-fields');
    toggleVisibility('has_web', 'web-field');
    toggleVisibility('auto_signature', 'signature-fields');
    toggleVisibility('enable_sepa', 'sepa-fields');

    const avatarUpload = document.getElementById('avatar-upload');
    const avatarInput = document.getElementById('avatar');
    if (avatarUpload && avatarInput) {
      avatarUpload.addEventListener('click', () => {
        avatarInput.click();
      });

      avatarInput.addEventListener('change', () => {
        const label = avatarUpload.querySelector('.file-label');
        if (label) {
          label.textContent = avatarInput.files && avatarInput.files.length
            ? 'Imagen seleccionada'
            : '+ Añadir imagen de perfil';
        }
      });
    }

    document.querySelectorAll('.password-toggle').forEach((toggle) => {
      toggle.addEventListener('click', () => {
        const targetId = toggle.getAttribute('id') === 'toggle-password' ? 'password' : 'confirm_password';
        const input = document.getElementById(targetId);
        if (!input) {
          return;
        }

        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        toggle.textContent = isPassword ? '🔒' : '👁️';
      });
    });

    showCurrentStep();
    updateProgress();
  });
})();
