(() => {
  document.addEventListener('DOMContentLoaded', () => {
    document.body.classList.add('register-body');

    const tabs = document.querySelectorAll('.tabs__link');
    const steps = document.querySelectorAll('.form-step');
    const connectors = document.querySelectorAll('.register-page__tabs .connector');
    const navButtons = document.querySelectorAll('[data-nav]');
    const scrollContainer = document.querySelector('.register-page__scroll');
    const registerBtn = document.getElementById('register-btn');

    const emailField = document.getElementById('access_email');
    const accountTypeField = document.getElementById('account_type');
    const companyField = document.getElementById('company_field');
    const firstNameField = document.getElementById('first_name');
    const lastNameField = document.getElementById('last_name');
    const phoneField = document.getElementById('phone');
    const signatureCheckbox = document.getElementById('auto_signature');
    const signatureFields = document.getElementById('signature_fields');
    const sepaCheckbox = document.getElementById('enable_sepa');
    const sepaFields = document.getElementById('sepa_fields');
    const profileUpload = document.getElementById('profile_upload');
    const profileInput = document.getElementById('profile_image');

    const summaryEmail = document.getElementById('summary_email');
    const summaryAccountType = document.getElementById('summary_account_type');
    const summaryCompanyRow = document.getElementById('summary_company_row');
    const summaryCompany = document.getElementById('summary_company');
    const summaryFullName = document.getElementById('summary_full_name');
    const summaryPhone = document.getElementById('summary_phone');
    const summarySignature = document.getElementById('summary_signature');
    const summarySepa = document.getElementById('summary_sepa');

    if (!steps.length) {
      return;
    }

    let currentStep = 1;
    const totalSteps = steps.length;

    const setStepVisibility = () => {
      steps.forEach((section) => {
        const stepNumber = Number(section.dataset.step);
        section.classList.toggle('active', stepNumber === currentStep);
      });
    };

    const updateTabs = () => {
      tabs.forEach((tab) => {
        const stepNumber = Number(tab.dataset.step);
        tab.classList.toggle('active', stepNumber === currentStep);
        tab.classList.toggle('completed', stepNumber < currentStep);
      });

      connectors.forEach((connector, index) => {
        connector.classList.toggle('connector--active', currentStep - 1 > index);
      });
    };

    const goToStep = (stepNumber) => {
      const clamped = Math.max(1, Math.min(stepNumber, totalSteps));
      currentStep = clamped;
      setStepVisibility();
      updateTabs();
      if (scrollContainer) {
        scrollContainer.scrollTop = 0;
      }
    };

    const getAccountTypeLabel = () => {
      if (!accountTypeField) {
        return '';
      }

      const selectedOption = accountTypeField.options[accountTypeField.selectedIndex];
      return selectedOption ? selectedOption.textContent.trim() : '';
    };

    const prepareSummary = () => {
      if (summaryEmail && emailField) {
        summaryEmail.textContent = emailField.value || '—';
      }

      if (summaryAccountType) {
        summaryAccountType.textContent = getAccountTypeLabel() || '—';
      }

      const companyValue = document.getElementById('company_name')?.value ?? '';
      if (summaryCompanyRow) {
        summaryCompanyRow.style.display = companyValue && accountTypeField?.value === 'professional' ? 'block' : 'none';
      }
      if (summaryCompany) {
        summaryCompany.textContent = companyValue || '—';
      }

      if (summaryFullName) {
        const nameParts = [firstNameField?.value ?? '', lastNameField?.value ?? '']
          .map((part) => part.trim())
          .filter(Boolean);
        summaryFullName.textContent = nameParts.length ? nameParts.join(' ') : '—';
      }

      if (summaryPhone) {
        summaryPhone.textContent = phoneField?.value || '—';
      }

      if (summarySignature) {
        summarySignature.textContent = signatureCheckbox?.checked ? 'Activado' : 'No añadido';
      }

      if (summarySepa) {
        summarySepa.textContent = sepaCheckbox?.checked ? 'Preparada' : 'No preparada';
      }
    };

    setStepVisibility();
    updateTabs();

    navButtons.forEach((button) => {
      button.addEventListener('click', (event) => {
        const direction = event.currentTarget.getAttribute('data-nav');
        if (!direction) {
          return;
        }

        if (direction === 'next' && currentStep < totalSteps) {
          if (currentStep + 1 === totalSteps) {
            prepareSummary();
          }
          goToStep(currentStep + 1);
        }

        if (direction === 'prev' && currentStep > 1) {
          goToStep(currentStep - 1);
        }
      });
    });

    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        const stepNumber = Number(tab.dataset.step);
        if (Number.isNaN(stepNumber)) {
          return;
        }

        if (stepNumber === totalSteps) {
          prepareSummary();
        }

        goToStep(stepNumber);
      });
    });

    document.querySelectorAll('.password-toggle').forEach((toggle) => {
      toggle.addEventListener('click', () => {
        const targetId = toggle.getAttribute('data-target');
        if (!targetId) {
          return;
        }
        const input = document.getElementById(targetId);
        if (!input) {
          return;
        }
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        toggle.textContent = isPassword ? '🔒' : '👁️';
      });
    });

    if (accountTypeField && companyField) {
      const companyInput = companyField;
      const toggleCompanyVisibility = () => {
        const shouldShow = accountTypeField.value === 'professional';
        companyInput.classList.toggle('is-visible', shouldShow);
      };

      toggleCompanyVisibility();
      accountTypeField.addEventListener('change', () => {
        toggleCompanyVisibility();
      });
    }

    if (signatureCheckbox && signatureFields) {
      signatureCheckbox.addEventListener('change', () => {
        signatureFields.classList.toggle('is-visible', signatureCheckbox.checked);
      });
    }

    if (sepaCheckbox && sepaFields) {
      sepaCheckbox.addEventListener('change', () => {
        sepaFields.classList.toggle('is-visible', sepaCheckbox.checked);
      });
    }

    if (profileUpload && profileInput) {
      profileUpload.addEventListener('click', () => {
        profileInput.click();
      });

      profileInput.addEventListener('change', () => {
        const label = profileUpload.querySelector('.register-upload__label');
        if (label) {
          label.textContent = profileInput.files?.length ? 'Imagen seleccionada' : '+ Añadir imagen de perfil';
        }
      });
    }

    if (registerBtn) {
      registerBtn.addEventListener('click', () => {
        const terms = document.getElementById('terms');
        if (terms && !terms.checked) {
          window.alert('Acepta los términos y condiciones para continuar.');
          return;
        }

        prepareSummary();
        window.alert('Registro enviado correctamente.');
      });
    }
  });
})();
