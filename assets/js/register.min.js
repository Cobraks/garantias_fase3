(() => {
  const REQUIRED_MESSAGE = 'Este campo es obligatorio.';
  const PASSWORD_MESSAGE = 'La contraseña debe tener al menos 8 caracteres, incluir un número y un símbolo.';
  const PASSWORD_MISMATCH_MESSAGE = 'Las contraseñas no coinciden.';
  const EMAIL_EXISTS_MESSAGE = 'Esta cuenta ya está registrada.';
  const PHONE_MESSAGE = 'Introduce un teléfono válido (9 dígitos).';
  const POSTAL_CODE_MESSAGE = 'Código postal inválido.';
  const URL_MESSAGE = 'Introduce una URL válida (https://...).';
  const IBAN_MESSAGE = 'Introduce un IBAN válido.';
  const SWIFT_MESSAGE = 'Introduce un código SWIFT/BIC válido.';

  const CHANNEL_LABELS = {
    compraventa: 'Profesional (Compraventa)',
    concesionario: 'Profesional (Concesionario Oficial)',
    individual: 'Particular',
    agency: 'Gestoría',
  };

  const PROFESSIONAL_CHANNELS = new Set(['compraventa', 'concesionario']);
  const EMAIL_REGEX = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
  const POSTAL_CODE_REGEX = /^(0[1-9]|[1-4]\d|5[0-3])\d{3}$/;
  const PHONE_REGEX = /^[6-9]\d{8}$/;
  const SWIFT_REGEX = /^[A-Za-z]{4}[A-Za-z]{2}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/;

  const getRegisterConfig = () => window.__GO_REGISTER__ || {};

  const debounce = (fn, delay) => {
    let timeout;
    return (...args) => {
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => fn.apply(null, args), delay);
    };
  };

  const cleanDigits = (value, maxLength) => value.replace(/\D/g, '').slice(0, maxLength);

  const formatIban = (value) => {
    const raw = value.replace(/\s+/g, '').toUpperCase();
    return raw.replace(/(.{4})/g, '$1 ').trim();
  };

  const isValidIban = (value) => {
    const sanitized = value.replace(/\s+/g, '').toUpperCase();
    if (!/^[A-Z0-9]{15,34}$/.test(sanitized)) {
      return false;
    }
    const rearranged = sanitized.slice(4) + sanitized.slice(0, 4);
    const converted = rearranged.replace(/[A-Z]/g, (char) => String(char.charCodeAt(0) - 55));
    let remainder = 0;
    for (let i = 0; i < converted.length; i += 1) {
      const digit = Number(converted[i]);
      if (Number.isNaN(digit)) {
        return false;
      }
      remainder = (remainder * 10 + digit) % 97;
    }
    return remainder === 1;
  };

  document.addEventListener('DOMContentLoaded', () => {
    const steps = document.querySelectorAll('.step');
    const formSteps = document.querySelectorAll('.form-step');
    const progressBar = document.getElementById('progress-bar');
    const progressContainer = document.querySelector('.progress-container');
    const prevButtons = document.querySelectorAll('[data-prev-step]');
    const registerBtn = document.getElementById('register-btn');
    const verifyBtn = document.getElementById('verify-btn');

    const step1Next = document.querySelector('#step-1 [data-next-step]');
    const step2Next = document.querySelector('#step-2 [data-next-step]');

    const channelButtons = document.querySelectorAll('.channel-btn');
    const channelError = document.getElementById('channel-error');
    const companySection = document.getElementById('company-section');

    const emailField = document.getElementById('email');
    const phoneField = document.getElementById('phone');

    const postalCodeFields = [
      document.getElementById('company_postal_code'),
      document.getElementById('sepa_postal_code'),
    ].filter(Boolean);

    const sepaFields = [
      'sepa_name',
      'sepa_address',
      'sepa_postal_code',
      'sepa_city',
      'sepa_state',
    ].map((id) => document.getElementById(id));

    const sepaSwiftField = document.getElementById('sepa_swift');
    const sepaIbanField = document.getElementById('sepa_iban');
    const sepaCountryField = document.getElementById('sepa_country');

    const hasWorkshopField = document.getElementById('has_workshop');
    const hasWebField = document.getElementById('has_web');
    const autoSignatureField = document.getElementById('auto_signature');
    const enableSepaField = document.getElementById('enable_sepa');

    const workshopFields = {
      container: document.getElementById('workshop-fields'),
      name: document.getElementById('workshop_name'),
      address: document.getElementById('workshop_address'),
      contact: document.getElementById('workshop_contact'),
      phone: document.getElementById('workshop_phone'),
      email: document.getElementById('workshop_email'),
    };

    const signatureUpload = document.getElementById('signature-upload');
    const stampUpload = document.getElementById('stamp-upload');
    const signatureInput = document.getElementById('signature');
    const stampInput = document.getElementById('stamp');

    const sepaSection = document.getElementById('sepa-fields');
    const webFieldContainer = document.getElementById('web-field');

    const summary = {
      channel: document.getElementById('summary-channel'),
      tradeName: document.getElementById('summary-trade-name'),
      legalName: document.getElementById('summary-legal-name'),
      name: document.getElementById('summary-name'),
      email: document.getElementById('summary-email'),
      phone: document.getElementById('summary-phone'),
      web: document.getElementById('summary-web'),
      signature: document.getElementById('summary-signature'),
      sepaStatus: document.getElementById('summary-sepa-status'),
      workshopGroup: document.getElementById('summary-workshop'),
      workshopName: document.getElementById('summary-workshop-name'),
      workshopContact: document.getElementById('summary-workshop-contact'),
      workshopAddress: document.getElementById('summary-workshop-address'),
      workshopPhone: document.getElementById('summary-workshop-phone'),
      workshopEmail: document.getElementById('summary-workshop-email'),
      sepaGroup: document.getElementById('summary-sepa'),
      sepaName: document.getElementById('summary-sepa-name'),
      sepaAddress: document.getElementById('summary-sepa-address'),
      sepaIban: document.getElementById('summary-sepa-iban'),
    };

    const state = {
      currentStep: 1,
      totalSteps: 3,
      selectedChannel: null,
      emailStatus: 'empty',
      emailValue: '',
      emailCheckController: null,
      lastCheckedEmail: '',
      sepaEdited: new Set(),
    };
    const setButtonDisabled = (button, disabled) => {
      if (!button) return;
      button.disabled = disabled;
      button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    };

    const getInputContainer = (element) => (element ? element.closest('.input-container') : null);

    const setFieldError = (element, message) => {
      const container = getInputContainer(element);
      if (!container) return;
      container.classList.add('error');
      let error = container.querySelector('.error-message');
      if (!error) {
        error = document.createElement('p');
        error.className = 'error-message';
        container.appendChild(error);
      }
      error.textContent = message;
      if (element) {
        element.setAttribute('aria-invalid', 'true');
      }
    };

    const clearFieldError = (element) => {
      const container = getInputContainer(element);
      if (!container) return;
      container.classList.remove('error');
      const error = container.querySelector('.error-message');
      if (error) {
        error.remove();
      }
      if (element) {
        element.removeAttribute('aria-invalid');
      }
    };

    const setUploadError = (container, message) => {
      if (!container) return;
      container.classList.add('error');
      let error = container.querySelector('.error-message');
      if (!error) {
        error = document.createElement('p');
        error.className = 'error-message';
        container.appendChild(error);
      }
      error.textContent = message;
    };

    const clearUploadError = (container) => {
      if (!container) return;
      container.classList.remove('error');
      const error = container.querySelector('.error-message');
      if (error) {
        error.remove();
      }
    };

    const showChannelError = (message) => {
      if (!channelError) return;
      channelError.textContent = message;
      channelError.hidden = false;
    };

    const clearChannelError = () => {
      if (!channelError) return;
      channelError.textContent = '';
      channelError.hidden = true;
    };

    const updateProgress = () => {
      if (progressBar) {
        const progress = state.totalSteps > 1 ? ((state.currentStep - 1) / (state.totalSteps - 1)) * 100 : 0;
        progressBar.style.width = `${progress}%`;
      }

      steps.forEach((step) => {
        const stepNumber = Number(step.getAttribute('data-step'));
        step.classList.remove('active', 'completed');
        if (stepNumber < state.currentStep) {
          step.classList.add('completed');
        } else if (stepNumber === state.currentStep) {
          step.classList.add('active');
        }
      });
    };

    const showCurrentStep = () => {
      formSteps.forEach((step) => {
        step.classList.remove('active');
      });
      const activeStep = document.getElementById(`step-${state.currentStep}`);
      if (activeStep) {
        activeStep.classList.add('active');
      }
    };

    const goToStep = (stepNumber) => {
      state.currentStep = Math.max(1, Math.min(stepNumber, state.totalSteps));
      showCurrentStep();
      updateProgress();
      if (progressContainer) {
        progressContainer.style.display = '';
      }
      if (state.currentStep === 3) {
        updateSummary();
      }
    };

    const getValue = (id) => {
      const element = document.getElementById(id);
      return element ? element.value.trim() : '';
    };

    const combineName = () => {
      const first = getValue('first_name');
      const last = getValue('last_name');
      return [first, last].filter(Boolean).join(' ');
    };

    const formatAddress = (address, postal, city, province) => {
      const parts = [address, [postal, city].filter(Boolean).join(' '), province].filter(Boolean);
      return parts.length ? parts.join(', ') : '—';
    };

    const handleEmailStatusChange = (status) => {
      state.emailStatus = status;
      updateStep1ButtonState();
    };

    const checkEmailAvailability = async () => {
      if (!emailField) return;
      const value = emailField.value.trim();
      if (!value || !EMAIL_REGEX.test(value)) {
        return;
      }
      if (value === state.lastCheckedEmail && (state.emailStatus === 'available' || state.emailStatus === 'exists')) {
        return;
      }

      if (state.emailCheckController) {
        state.emailCheckController.abort();
      }

      const controller = new AbortController();
      state.emailCheckController = controller;
      state.lastCheckedEmail = value;
      handleEmailStatusChange('pending');

      const base = (getRegisterConfig().rest && getRegisterConfig().rest.root) || `${window.location.origin}/wp-json/go/public/v1/`;
      const endpoint = `${base.replace(/\/?$/, '/') }register/check-email?email=${encodeURIComponent(value)}`;

      try {
        const response = await window.fetch(endpoint, { signal: controller.signal });
        if (controller.signal.aborted) {
          return;
        }
        if (!response.ok) {
          throw new Error('Request failed');
        }
        const data = await response.json();
        if (emailField.value.trim() !== value) {
          return;
        }
        if (data && data.exists) {
          setFieldError(emailField, EMAIL_EXISTS_MESSAGE);
          handleEmailStatusChange('exists');
        } else {
          clearFieldError(emailField);
          handleEmailStatusChange('available');
        }
      } catch (error) {
        if (!controller.signal.aborted) {
          console.error('[checkEmailAvailability]', error);
          handleEmailStatusChange('error');
        }
      }
    };

    const debouncedEmailCheck = debounce(checkEmailAvailability, 400);

    const validateRequired = (element, showError, message = REQUIRED_MESSAGE) => {
      if (!element) return true;
      if (element.value.trim() === '') {
        if (showError) {
          setFieldError(element, message);
        }
        return false;
      }
      clearFieldError(element);
      return true;
    };

    const validateEmailInput = (showError) => {
      if (!emailField) return true;
      const value = emailField.value.trim();
      if (!value) {
        if (showError) {
          setFieldError(emailField, REQUIRED_MESSAGE);
        }
        handleEmailStatusChange('empty');
        return false;
      }
      if (!EMAIL_REGEX.test(value)) {
        if (showError) {
          setFieldError(emailField, 'Introduce un correo electrónico válido.');
        }
        handleEmailStatusChange('invalid');
        return false;
      }
      clearFieldError(emailField);
      if (state.emailStatus !== 'pending' && state.emailStatus !== 'available' && state.emailStatus !== 'exists') {
        handleEmailStatusChange('pending');
      }
      return true;
    };

    const validatePhoneInput = (element, showError) => {
      if (!element) return true;
      const value = element.value.trim();
      if (!value) {
        if (showError) {
          setFieldError(element, REQUIRED_MESSAGE);
        }
        return false;
      }
      if (!PHONE_REGEX.test(value)) {
        if (showError) {
          setFieldError(element, PHONE_MESSAGE);
        }
        return false;
      }
      clearFieldError(element);
      return true;
    };

    const validatePostalCodeInput = (element, showError, required = true) => {
      if (!element) return true;
      const value = element.value.trim();
      if (!value) {
        if (required && showError) {
          setFieldError(element, REQUIRED_MESSAGE);
        }
        return !required;
      }
      if (!POSTAL_CODE_REGEX.test(value)) {
        if (showError) {
          setFieldError(element, POSTAL_CODE_MESSAGE);
        }
        return false;
      }
      clearFieldError(element);
      return true;
    };

    const validatePasswordInput = (showError) => {
      const password = document.getElementById('password');
      if (!password) return true;
      const value = password.value;
      if (!value) {
        if (showError) {
          setFieldError(password, REQUIRED_MESSAGE);
        }
        return false;
      }
      const hasLength = value.length >= 8;
      const hasNumber = /\d/.test(value);
      const hasSymbol = /[^A-Za-z0-9]/.test(value);
      if (!hasLength || !hasNumber || !hasSymbol) {
        if (showError) {
          setFieldError(password, PASSWORD_MESSAGE);
        }
        return false;
      }
      clearFieldError(password);
      return true;
    };

    const validatePasswordConfirmation = (showError) => {
      const password = document.getElementById('password');
      const confirm = document.getElementById('confirm_password');
      if (!password || !confirm) return true;
      if (confirm.value.trim() === '') {
        if (showError) {
          setFieldError(confirm, REQUIRED_MESSAGE);
        }
        return false;
      }
      if (password.value !== confirm.value) {
        if (showError) {
          setFieldError(confirm, PASSWORD_MISMATCH_MESSAGE);
        }
        return false;
      }
      clearFieldError(confirm);
      return true;
    };

    const validateUrlInput = (element, showError, required = false) => {
      if (!element) return true;
      const value = element.value.trim();
      if (!value) {
        if (required && showError) {
          setFieldError(element, REQUIRED_MESSAGE);
        } else if (!required) {
          clearFieldError(element);
        }
        return !required;
      }
      try {
        const parsed = new URL(value);
        if (!/^https?:$/.test(parsed.protocol)) {
          throw new Error('Invalid protocol');
        }
        clearFieldError(element);
        return true;
      } catch (error) {
        if (showError) {
          setFieldError(element, URL_MESSAGE);
        }
        return false;
      }
    };

    const validateSwiftInput = (showError) => {
      if (!sepaSwiftField) return true;
      const value = sepaSwiftField.value.trim().toUpperCase();
      sepaSwiftField.value = value;
      if (!value) {
        if (showError) {
          setFieldError(sepaSwiftField, REQUIRED_MESSAGE);
        }
        return false;
      }
      if (!SWIFT_REGEX.test(value)) {
        if (showError) {
          setFieldError(sepaSwiftField, SWIFT_MESSAGE);
        }
        return false;
      }
      clearFieldError(sepaSwiftField);
      return true;
    };

    const validateIbanInput = (showError) => {
      if (!sepaIbanField) return true;
      const value = sepaIbanField.value.trim();
      if (!value) {
        if (showError) {
          setFieldError(sepaIbanField, REQUIRED_MESSAGE);
        }
        return false;
      }
      if (!isValidIban(value)) {
        if (showError) {
          setFieldError(sepaIbanField, IBAN_MESSAGE);
        }
        return false;
      }
      clearFieldError(sepaIbanField);
      return true;
    };

    const validateCompanySection = (showError) => {
      if (!state.selectedChannel || !PROFESSIONAL_CHANNELS.has(state.selectedChannel)) {
        if (companySection) {
          companySection.hidden = true;
          companySection.setAttribute('aria-hidden', 'true');
        }
        return true;
      }
      const fields = [
        'company_trade_name',
        'company_legal_name',
        'company_cif',
        'company_address',
        'company_postal_code',
        'company_city',
        'company_province',
      ];
      let valid = true;
      fields.forEach((id) => {
        const element = document.getElementById(id);
        if (!element) {
          return;
        }
        if (id.endsWith('postal_code')) {
          if (!validatePostalCodeInput(element, showError)) {
            valid = false;
          }
        } else if (!validateRequired(element, showError)) {
          valid = false;
        }
      });
      return valid;
    };
    const validateWorkshopSection = (showError) => {
      if (!hasWorkshopField || !hasWorkshopField.checked) {
        return true;
      }
      let valid = true;
      if (!validateRequired(workshopFields.name, showError)) {
        valid = false;
      }
      if (!validateRequired(workshopFields.address, showError)) {
        valid = false;
      }
      if (!validateRequired(workshopFields.contact, showError)) {
        valid = false;
      }
      if (!validatePhoneInput(workshopFields.phone, showError)) {
        valid = false;
      }
      if (workshopFields.email) {
        const value = workshopFields.email.value.trim();
        if (!value) {
          if (showError) {
            setFieldError(workshopFields.email, REQUIRED_MESSAGE);
          }
          valid = false;
        } else if (!EMAIL_REGEX.test(value)) {
          if (showError) {
            setFieldError(workshopFields.email, 'Introduce un correo electrónico válido.');
          }
          valid = false;
        } else {
          clearFieldError(workshopFields.email);
        }
      }
      return valid;
    };

    const validateSignatureSection = (showError) => {
      if (!autoSignatureField || !autoSignatureField.checked) {
        return true;
      }
      let valid = true;
      if (!signatureInput || !signatureInput.files || !signatureInput.files.length) {
        if (showError) {
          setUploadError(signatureUpload, REQUIRED_MESSAGE);
        }
        valid = false;
      } else {
        clearUploadError(signatureUpload);
      }
      if (!stampInput || !stampInput.files || !stampInput.files.length) {
        if (showError) {
          setUploadError(stampUpload, REQUIRED_MESSAGE);
        }
        valid = false;
      } else {
        clearUploadError(stampUpload);
      }
      return valid;
    };

    const validateSepaSection = (showError) => {
      if (!enableSepaField || !enableSepaField.checked) {
        return true;
      }
      let valid = true;
      sepaFields.forEach((field) => {
        if (!field) {
          return;
        }
        if (!validateRequired(field, showError)) {
          valid = false;
        }
        if (field.id.endsWith('postal_code') && !validatePostalCodeInput(field, showError)) {
          valid = false;
        }
      });
      if (!validateRequired(sepaCountryField, showError)) {
        valid = false;
      }
      if (!validateSwiftInput(showError)) {
        valid = false;
      }
      if (!validateIbanInput(showError)) {
        valid = false;
      }
      return valid;
    };

    const validateStep1 = (showError) => {
      let valid = true;
      if (!state.selectedChannel) {
        if (showError) {
          showChannelError('Selecciona un canal de venta.');
        }
        valid = false;
      } else {
        clearChannelError();
      }

      ['first_name', 'last_name'].forEach((id) => {
        const field = document.getElementById(id);
        if (!validateRequired(field, showError)) {
          valid = false;
        }
      });

      if (!validatePhoneInput(phoneField, showError)) {
        valid = false;
      }
      if (!validateEmailInput(showError)) {
        valid = false;
      }
      if (!validatePasswordInput(showError)) {
        valid = false;
      }
      if (!validatePasswordConfirmation(showError)) {
        valid = false;
      }
      if (!validateCompanySection(showError)) {
        valid = false;
      }

      if (state.emailStatus === 'exists') {
        valid = false;
      }
      if (state.emailStatus === 'pending') {
        valid = false;
      }
      return valid;
    };

    const validateStep2 = (showError) => {
      let valid = true;
      if (!validateWorkshopSection(showError)) {
        valid = false;
      }
      if (hasWebField && hasWebField.checked) {
        const urlInput = document.getElementById('web_url');
        if (!validateUrlInput(urlInput, showError, true)) {
          valid = false;
        }
      } else if (webFieldContainer) {
        const urlInput = document.getElementById('web_url');
        if (urlInput) {
          clearFieldError(urlInput);
        }
      }
      if (!validateSignatureSection(showError)) {
        valid = false;
      }
      if (!validateSepaSection(showError)) {
        valid = false;
      }
      return valid;
    };

    const updateStep1ButtonState = () => {
      const isValid = validateStep1(false);
      setButtonDisabled(step1Next, !isValid);
    };

    const updateStep2ButtonState = () => {
      const isValid = validateStep2(false);
      setButtonDisabled(step2Next, !isValid);
    };

    const markSepaEdited = (field) => {
      if (!field) return;
      state.sepaEdited.add(field.id);
    };

    const prefillSepaFields = () => {
      const entries = [
        { target: 'sepa_name', get: combineName },
        { target: 'sepa_address', source: 'company_address' },
        { target: 'sepa_postal_code', source: 'company_postal_code' },
        { target: 'sepa_city', source: 'company_city' },
        { target: 'sepa_state', source: 'company_province' },
      ];
      entries.forEach(({ target, source, get }) => {
        const field = document.getElementById(target);
        if (!field || state.sepaEdited.has(field.id)) {
          return;
        }
        const value = typeof get === 'function' ? get() : getValue(source || '');
        if (value && !field.value) {
          field.value = value;
        }
      });
    };

    const updateSummary = () => {
      if (summary.channel) {
        summary.channel.textContent = CHANNEL_LABELS[state.selectedChannel] || '—';
      }
      if (summary.tradeName) {
        summary.tradeName.textContent = getValue('company_trade_name') || '—';
      }
      if (summary.legalName) {
        summary.legalName.textContent = getValue('company_legal_name') || '—';
      }
      if (summary.name) {
        const name = combineName();
        summary.name.textContent = name || '—';
      }
      if (summary.email && emailField) {
        summary.email.textContent = emailField.value.trim() || '—';
      }
      if (summary.phone && phoneField) {
        summary.phone.textContent = phoneField.value.trim() || '—';
      }
      if (summary.web) {
        const urlInput = document.getElementById('web_url');
        summary.web.textContent = urlInput && urlInput.value.trim() ? urlInput.value.trim() : '—';
      }
      if (summary.signature) {
        summary.signature.textContent = autoSignatureField && autoSignatureField.checked ? 'Activada' : 'No activada';
      }
      if (summary.sepaStatus) {
        summary.sepaStatus.textContent = enableSepaField && enableSepaField.checked ? 'Activada' : 'No activada';
      }

      if (summary.workshopGroup) {
        const isActive = hasWorkshopField && hasWorkshopField.checked;
        summary.workshopGroup.hidden = !isActive;
        summary.workshopGroup.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        if (isActive) {
          summary.workshopName.textContent = workshopFields.name?.value.trim() || '—';
          summary.workshopContact.textContent = workshopFields.contact?.value.trim() || '—';
          summary.workshopAddress.textContent = workshopFields.address?.value.trim() || '—';
          summary.workshopPhone.textContent = workshopFields.phone?.value.trim() || '—';
          summary.workshopEmail.textContent = workshopFields.email?.value.trim() || '—';
        }
      }

      if (summary.sepaGroup) {
        const isActive = enableSepaField && enableSepaField.checked;
        summary.sepaGroup.hidden = !isActive;
        summary.sepaGroup.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        if (isActive) {
          summary.sepaName.textContent = getValue('sepa_name') || '—';
          summary.sepaAddress.textContent = formatAddress(
            getValue('sepa_address'),
            getValue('sepa_postal_code'),
            getValue('sepa_city'),
            getValue('sepa_state')
          );
          const ibanValue = sepaIbanField ? sepaIbanField.value.trim() : '';
          summary.sepaIban.textContent = ibanValue ? formatIban(ibanValue) : '—';
        }
      }
    };

    const handleChannelSelection = (channel) => {
      state.selectedChannel = channel;
      channelButtons.forEach((button) => {
        button.classList.toggle('active', button.getAttribute('data-channel') === channel);
      });
      if (companySection) {
        const showCompany = PROFESSIONAL_CHANNELS.has(channel);
        companySection.hidden = !showCompany;
        companySection.setAttribute('aria-hidden', showCompany ? 'false' : 'true');
      }
      clearChannelError();
      updateStep1ButtonState();
      updateSummary();
      if (enableSepaField && enableSepaField.checked) {
        prefillSepaFields();
      }
    };

    const toggleSection = (checkbox, section) => {
      if (!checkbox || !section) {
        return;
      }
      const update = () => {
        const isActive = checkbox.checked;
        section.classList.toggle('visible', isActive);
        section.hidden = !isActive;
        section.setAttribute('aria-hidden', isActive ? 'false' : 'true');
        if (!isActive) {
          section.querySelectorAll('.input-container').forEach((container) => {
            container.classList.remove('error');
            const error = container.querySelector('.error-message');
            if (error) {
              error.remove();
            }
          });
          section.querySelectorAll('.file-upload').forEach((container) => {
            clearUploadError(container);
          });
        } else if (checkbox === enableSepaField) {
          prefillSepaFields();
        }
        updateStep2ButtonState();
        updateSummary();
      };
      checkbox.addEventListener('change', update);
      update();
    };
    const step1Handler = () => {
      if (!validateStep1(true)) {
        return;
      }
      prefillSepaFields();
      goToStep(2);
    };

    const step2Handler = () => {
      if (!validateStep2(true)) {
        return;
      }
      updateSummary();
      goToStep(3);
    };

    if (step1Next) {
      step1Next.addEventListener('click', step1Handler);
    }

    if (step2Next) {
      step2Next.addEventListener('click', step2Handler);
    }

    prevButtons.forEach((button) => {
      button.addEventListener('click', () => {
        if (state.currentStep > 1) {
          state.currentStep -= 1;
          goToStep(state.currentStep);
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

    if (channelButtons.length) {
      channelButtons.forEach((button) => {
        button.addEventListener('click', () => {
          const channel = button.getAttribute('data-channel');
          handleChannelSelection(channel);
        });
      });
    }
    if (emailField) {
      emailField.addEventListener('input', () => {
        state.emailValue = emailField.value.trim();
        if (!emailField.value.trim()) {
          handleEmailStatusChange('empty');
        } else if (EMAIL_REGEX.test(emailField.value.trim())) {
          debouncedEmailCheck();
        } else {
          handleEmailStatusChange('invalid');
        }
        validateEmailInput(false);
        updateStep1ButtonState();
        updateSummary();
      });
      emailField.addEventListener('blur', () => {
        validateEmailInput(true);
        checkEmailAvailability();
      });
    }

    if (phoneField) {
      phoneField.addEventListener('input', () => {
        const caret = phoneField.selectionStart || phoneField.value.length;
        phoneField.value = cleanDigits(phoneField.value, 9);
        phoneField.setSelectionRange(caret, caret);
        updateStep1ButtonState();
        updateSummary();
      });
      phoneField.addEventListener('blur', () => {
        validatePhoneInput(phoneField, true);
      });
    }

    postalCodeFields.forEach((field) => {
      field.addEventListener('input', () => {
        field.value = cleanDigits(field.value, 5);
        updateStep1ButtonState();
        updateStep2ButtonState();
      });
      field.addEventListener('blur', () => {
        if (field.id === 'company_postal_code' && (!state.selectedChannel || !PROFESSIONAL_CHANNELS.has(state.selectedChannel))) {
          clearFieldError(field);
          return;
        }
        if (field.id.startsWith('sepa_') && (!enableSepaField || !enableSepaField.checked)) {
          clearFieldError(field);
          return;
        }
        validatePostalCodeInput(field, true);
      });
    });

    ['first_name', 'last_name', 'company_trade_name', 'company_legal_name', 'company_cif', 'company_address', 'company_city', 'company_province'].forEach((id) => {
      const field = document.getElementById(id);
      if (!field) return;
      field.addEventListener('input', () => {
        if (id === 'company_cif') {
          field.value = field.value.toUpperCase();
        }
        updateStep1ButtonState();
        updateSummary();
      });
      field.addEventListener('blur', () => {
        if (PROFESSIONAL_CHANNELS.has(state.selectedChannel || '')) {
          validateRequired(field, true);
        } else {
          clearFieldError(field);
        }
      });
    });

    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');

    if (passwordField) {
      passwordField.addEventListener('input', () => {
        validatePasswordInput(false);
        updateStep1ButtonState();
      });
      passwordField.addEventListener('blur', () => {
        validatePasswordInput(true);
      });
    }

    if (confirmPasswordField) {
      confirmPasswordField.addEventListener('input', () => {
        validatePasswordConfirmation(false);
        updateStep1ButtonState();
      });
      confirmPasswordField.addEventListener('blur', () => {
        validatePasswordConfirmation(true);
      });
    }

    ['workshop_name', 'workshop_address', 'workshop_contact', 'workshop_email'].forEach((id) => {
      const field = document.getElementById(id);
      if (!field) return;
      field.addEventListener('input', () => {
        updateStep2ButtonState();
        updateSummary();
      });
      field.addEventListener('blur', () => {
        if (hasWorkshopField && hasWorkshopField.checked) {
          validateRequired(field, true);
        } else {
          clearFieldError(field);
        }
      });
    });

    const workshopPhoneField = document.getElementById('workshop_phone');
    if (workshopPhoneField) {
      workshopPhoneField.addEventListener('input', () => {
        const caret = workshopPhoneField.selectionStart || workshopPhoneField.value.length;
        workshopPhoneField.value = cleanDigits(workshopPhoneField.value, 9);
        workshopPhoneField.setSelectionRange(caret, caret);
        updateStep2ButtonState();
        updateSummary();
      });
      workshopPhoneField.addEventListener('blur', () => {
        if (hasWorkshopField && hasWorkshopField.checked) {
          validatePhoneInput(workshopPhoneField, true);
        } else {
          clearFieldError(workshopPhoneField);
        }
      });
    }

    const webUrlField = document.getElementById('web_url');
    if (webUrlField) {
      webUrlField.addEventListener('input', () => {
        updateStep2ButtonState();
        updateSummary();
      });
      webUrlField.addEventListener('blur', () => {
        if (hasWebField && hasWebField.checked) {
          validateUrlInput(webUrlField, true, true);
        } else {
          clearFieldError(webUrlField);
        }
      });
    }
    sepaFields.forEach((field) => {
      if (!field) return;
      field.addEventListener('input', () => {
        markSepaEdited(field);
        updateStep2ButtonState();
        updateSummary();
      });
      field.addEventListener('blur', () => {
        if (enableSepaField && enableSepaField.checked) {
          validateRequired(field, true);
        } else {
          clearFieldError(field);
        }
      });
    });

    if (sepaSwiftField) {
      sepaSwiftField.addEventListener('input', () => {
        sepaSwiftField.value = sepaSwiftField.value.toUpperCase();
        markSepaEdited(sepaSwiftField);
        updateStep2ButtonState();
        updateSummary();
      });
      sepaSwiftField.addEventListener('blur', () => {
        if (enableSepaField && enableSepaField.checked) {
          validateSwiftInput(true);
        } else {
          clearFieldError(sepaSwiftField);
        }
      });
    }

    if (sepaIbanField) {
      sepaIbanField.addEventListener('input', () => {
        markSepaEdited(sepaIbanField);
        updateStep2ButtonState();
        updateSummary();
      });
      sepaIbanField.addEventListener('blur', () => {
        if (enableSepaField && enableSepaField.checked) {
          validateIbanInput(true);
        } else {
          clearFieldError(sepaIbanField);
        }
      });
    }

    if (sepaCountryField) {
      sepaCountryField.addEventListener('input', () => {
        markSepaEdited(sepaCountryField);
        updateStep2ButtonState();
        updateSummary();
      });
      sepaCountryField.addEventListener('blur', () => {
        if (enableSepaField && enableSepaField.checked) {
          validateRequired(sepaCountryField, true);
        } else {
          clearFieldError(sepaCountryField);
        }
      });
    }

    if (hasWorkshopField) {
      hasWorkshopField.addEventListener('change', () => {
        updateStep2ButtonState();
        updateSummary();
      });
    }
    if (hasWebField) {
      hasWebField.addEventListener('change', () => {
        updateStep2ButtonState();
        updateSummary();
      });
    }
    if (autoSignatureField) {
      autoSignatureField.addEventListener('change', () => {
        updateStep2ButtonState();
        updateSummary();
      });
    }
    if (enableSepaField) {
      enableSepaField.addEventListener('change', () => {
        updateStep2ButtonState();
        updateSummary();
        if (enableSepaField.checked) {
          prefillSepaFields();
        }
      });
    }

    toggleSection(hasWorkshopField, workshopFields.container);
    toggleSection(hasWebField, webFieldContainer);
    toggleSection(autoSignatureField, document.getElementById('signature-fields'));
    toggleSection(enableSepaField, sepaSection);
    const avatarUpload = document.getElementById('avatar-upload');
    const avatarInput = document.getElementById('avatar');
    const avatarPreview = document.getElementById('avatar-preview');
    const avatarPreviewImage = avatarPreview ? avatarPreview.querySelector('img') : null;

    const resetAvatarPreview = () => {
      if (avatarPreview) {
        avatarPreview.hidden = true;
        avatarPreview.setAttribute('aria-hidden', 'true');
      }
      if (avatarPreviewImage) {
        avatarPreviewImage.removeAttribute('src');
      }
    };

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

        if (!avatarPreview || !avatarPreviewImage) {
          return;
        }

        if (avatarInput.files && avatarInput.files.length) {
          const [file] = avatarInput.files;
          if (file && file.type && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.addEventListener('load', () => {
              if (typeof reader.result === 'string') {
                avatarPreviewImage.src = reader.result;
                avatarPreview.hidden = false;
                avatarPreview.setAttribute('aria-hidden', 'false');
              }
            });
            reader.readAsDataURL(file);
          } else {
            resetAvatarPreview();
          }
        } else {
          resetAvatarPreview();
        }
      });
    }

    const setupImageUpload = (containerId, inputId, previewId) => {
      const container = document.getElementById(containerId);
      const input = document.getElementById(inputId);
      const preview = document.getElementById(previewId);
      const previewImage = preview ? preview.querySelector('img') : null;
      if (!container || !input) {
        return;
      }
      const label = container.querySelector('.file-label');
      const defaultLabel = label ? label.textContent : '';

      const resetPreview = () => {
        if (preview) {
          preview.hidden = true;
          preview.setAttribute('aria-hidden', 'true');
        }
        if (previewImage) {
          previewImage.removeAttribute('src');
        }
        if (label) {
          label.textContent = defaultLabel;
        }
        clearUploadError(container);
        updateStep2ButtonState();
        updateSummary();
      };

      container.addEventListener('click', (event) => {
        if (event.target !== input) {
          input.click();
        }
      });

      input.addEventListener('change', () => {
        if (!input.files || !input.files.length) {
          resetPreview();
          return;
        }
        const [file] = input.files;
        if (!file || !file.type || !file.type.startsWith('image/')) {
          resetPreview();
          setUploadError(container, 'Formato de archivo no válido.');
          return;
        }
        const reader = new FileReader();
        reader.addEventListener('load', () => {
          if (typeof reader.result === 'string') {
            if (preview && previewImage) {
              previewImage.src = reader.result;
              preview.hidden = false;
              preview.setAttribute('aria-hidden', 'false');
            }
            if (label) {
              label.textContent = file.name;
            }
            clearUploadError(container);
            updateStep2ButtonState();
            updateSummary();
          }
        });
        reader.readAsDataURL(file);
      });
    };

    setupImageUpload('signature-upload', 'signature', 'signature-preview');
    setupImageUpload('stamp-upload', 'stamp', 'stamp-preview');

    document.querySelectorAll('.password-toggle').forEach((toggle) => {
      const targetId = toggle.getAttribute('data-target');
      const input = targetId ? document.getElementById(targetId) : null;
      if (!input) {
        return;
      }
      toggle.addEventListener('click', () => {
        const revealPassword = input.type === 'password';
        input.type = revealPassword ? 'text' : 'password';
        toggle.classList.toggle('is-active', revealPassword);
        toggle.setAttribute('aria-label', revealPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
      });
    });

    document.querySelectorAll('.help-trigger').forEach((trigger) => {
      const targetId = trigger.getAttribute('data-help-target');
      const panel = targetId ? document.getElementById(targetId) : null;
      if (!panel) {
        return;
      }
      const closeBtn = panel.querySelector('[data-help-dismiss]');
      const setExpanded = (expanded) => {
        trigger.classList.toggle('is-active', expanded);
        trigger.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        panel.hidden = !expanded;
        panel.classList.toggle('is-active', expanded);
      };
      trigger.addEventListener('click', () => {
        const willExpand = !trigger.classList.contains('is-active');
        setExpanded(willExpand);
      });
      if (closeBtn) {
        closeBtn.addEventListener('click', () => {
          setExpanded(false);
        });
      }
      setExpanded(false);
    });

    showCurrentStep();
    updateProgress();
    updateStep1ButtonState();
    updateStep2ButtonState();
    updateSummary();
  });
})();
