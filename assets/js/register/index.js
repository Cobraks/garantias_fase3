(() => {
  document.addEventListener('DOMContentLoaded', () => {
    const steps = document.querySelectorAll('.step');
    const formSteps = document.querySelectorAll('.form-step');
    const progressBar = document.getElementById('progress-bar');
    const nextButtons = document.querySelectorAll('[data-next-step]');
    const prevButtons = document.querySelectorAll('[data-prev-step]');
    const registerBtn = document.getElementById('register-btn');
    const verifyBtn = document.getElementById('verify-btn');
    const progressContainer = document.querySelector('.progress-container');

    if (!steps.length || !formSteps.length) {
      return;
    }

    let currentStep = 1;
    const totalSteps = 3;

    const showStep = (stepNumber) => {
      formSteps.forEach((step) => {
        step.classList.remove('active');
      });

      const target = document.getElementById(`step-${stepNumber}`);
      if (target) {
        target.classList.add('active');
      }
    };

    const updateProgress = () => {
      const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
      if (progressBar) {
        progressBar.style.width = `${progress}%`;
      }

      steps.forEach((step) => {
        const stepNum = Number(step.dataset.step);
        step.classList.remove('active', 'completed');
        if (Number.isNaN(stepNum)) {
          return;
        }

        if (stepNum < currentStep) {
          step.classList.add('completed');
        } else if (stepNum === currentStep) {
          step.classList.add('active');
        }
      });

      showStep(currentStep);
    };

    nextButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (currentStep < totalSteps) {
          currentStep += 1;
          updateProgress();
          return;
        }

        if (currentStep === totalSteps) {
          showStep(totalSteps);
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
        }
      });
    });

    prevButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (currentStep > 1) {
          currentStep -= 1;
          updateProgress();
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

        const emailField = document.getElementById('email');
        const emailSent = document.getElementById('email-sent');
        if (emailField && emailSent) {
          emailSent.textContent = emailField.value;
        }
      });
    }

    if (verifyBtn) {
      verifyBtn.addEventListener('click', () => {
        const codeField = document.getElementById('verification_code');
        if (codeField && codeField.value) {
          window.alert('¡Cuenta verificada con éxito! Ahora puedes iniciar sesión.');
        } else {
          window.alert('Por favor, introduce el código de verificación.');
        }
      });
    }

    const channelButtons = document.querySelectorAll('.channel-btn');
    const companyField = document.getElementById('company-field');

    if (channelButtons.length) {
      channelButtons.forEach((button) => {
        button.addEventListener('click', () => {
          channelButtons.forEach((btn) => btn.classList.remove('active'));
          button.classList.add('active');

          if (companyField) {
            if (button.dataset.channel === 'professional') {
              companyField.classList.add('visible');
            } else {
              companyField.classList.remove('visible');
            }
          }
        });
      });
    }

    const workshopCheckbox = document.getElementById('has_workshop');
    const workshopFields = document.getElementById('workshop-fields');
    if (workshopCheckbox && workshopFields) {
      workshopCheckbox.addEventListener('change', (event) => {
        if (event.currentTarget.checked) {
          workshopFields.classList.add('visible');
        } else {
          workshopFields.classList.remove('visible');
        }
      });
    }

    const webCheckbox = document.getElementById('has_web');
    const webField = document.getElementById('web-field');
    if (webCheckbox && webField) {
      webCheckbox.addEventListener('change', (event) => {
        if (event.currentTarget.checked) {
          webField.classList.add('visible');
        } else {
          webField.classList.remove('visible');
        }
      });
    }

    const signatureCheckbox = document.getElementById('auto_signature');
    const signatureFields = document.getElementById('signature-fields');
    if (signatureCheckbox && signatureFields) {
      signatureCheckbox.addEventListener('change', (event) => {
        if (event.currentTarget.checked) {
          signatureFields.classList.add('visible');
        } else {
          signatureFields.classList.remove('visible');
        }
      });
    }

    const sepaCheckbox = document.getElementById('enable_sepa');
    const sepaFields = document.getElementById('sepa-fields');
    if (sepaCheckbox && sepaFields) {
      sepaCheckbox.addEventListener('change', (event) => {
        if (event.currentTarget.checked) {
          sepaFields.classList.add('visible');
        } else {
          sepaFields.classList.remove('visible');
        }
      });
    }

    const avatarUpload = document.getElementById('avatar-upload');
    const avatarInput = document.getElementById('avatar');
    if (avatarUpload && avatarInput) {
      avatarUpload.addEventListener('click', () => {
        avatarInput.click();
      });

      avatarInput.addEventListener('change', () => {
        const label = avatarUpload.querySelector('.file-label');
        if (avatarInput.files && avatarInput.files.length > 0 && label) {
          label.textContent = 'Imagen seleccionada';
        }
      });
    }

    const togglePassword = document.getElementById('toggle-password');
    const passwordInput = document.getElementById('password');
    if (togglePassword && passwordInput) {
      togglePassword.addEventListener('click', () => {
        if (passwordInput.type === 'password') {
          passwordInput.type = 'text';
          togglePassword.textContent = '🔒';
        } else {
          passwordInput.type = 'password';
          togglePassword.textContent = '👁️';
        }
      });
    }

    const toggleConfirmPassword = document.getElementById('toggle-confirm-password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    if (toggleConfirmPassword && confirmPasswordInput) {
      toggleConfirmPassword.addEventListener('click', () => {
        if (confirmPasswordInput.type === 'password') {
          confirmPasswordInput.type = 'text';
          toggleConfirmPassword.textContent = '🔒';
        } else {
          confirmPasswordInput.type = 'password';
          toggleConfirmPassword.textContent = '👁️';
        }
      });
    }

    updateProgress();
  });
})();
