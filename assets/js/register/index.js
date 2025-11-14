(() => {
  const REQUIRED_MESSAGE = 'Este campo es obligatorio.';
  const PASSWORD_MESSAGE = 'La contraseña debe tener al menos 8 caracteres, incluir un número y un símbolo.';
  const PASSWORD_MISMATCH_MESSAGE = 'Las contraseñas no coinciden.';
  const EMAIL_EXISTS_MESSAGE = 'Correo electrónico ya registrado';
  const PHONE_MESSAGE = 'Introduce un teléfono válido (9 dígitos).';
  const POSTAL_CODE_MESSAGE = 'Código postal inválido.';
  const URL_MESSAGE = 'Introduce una URL válida (https://...).';
  const IBAN_MESSAGE = 'Introduce un IBAN válido.';
  const SWIFT_MESSAGE = 'Introduce un código SWIFT/BIC válido.';
  const VERIFICATION_STORAGE_KEY = 'go360_register_verification';

  const CHANNEL_LABELS = {
    compraventa: 'Compraventa',
    concesionario: 'Concesionario Oficial',
    individual: 'Particular',
    agency: 'Gestoría',
  };

  const PROFESSIONAL_CHANNELS = new Set(['compraventa', 'concesionario']);
  const INDIVIDUAL_CHANNEL = 'individual';
  const STEP_SEQUENCE_DEFAULT = [1, 2, 3];
  const STEP_SEQUENCE_INDIVIDUAL = [1, 3];
  const EMAIL_REGEX = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
  const POSTAL_CODE_REGEX = /^(0[1-9]|[1-4]\d|5[0-3])\d{3}$/;
  const PHONE_REGEX = /^[6-9]\d{8}$/;
  const SWIFT_REGEX = /^[A-Za-z]{4}[A-Za-z]{2}[A-Za-z0-9]{2}([A-Za-z0-9]{3})?$/;

  const getRegisterConfig = () => window.__GO_REGISTER__ || {};
  const getSepaConfig = () => {
    const config = getRegisterConfig();
    const raw = config && typeof config === 'object' ? config.sepa : null;
    return raw && typeof raw === 'object' ? raw : {};
  };

  const sepaConfig = getSepaConfig();
  const sepaTemplateUrl = typeof sepaConfig.templateUrl === 'string' ? sepaConfig.templateUrl.trim() : '';
  const sepaCreditor = sepaConfig.creditor && typeof sepaConfig.creditor === 'object'
    ? sepaConfig.creditor
    : {};
  const sepaReferencePrefix = typeof sepaConfig.referencePrefix === 'string'
    && sepaConfig.referencePrefix.trim() !== ''
      ? sepaConfig.referencePrefix.trim().toUpperCase()
      : 'GO';
  const sepaFontkitUrl = typeof sepaConfig.fontkitUrl === 'string' ? sepaConfig.fontkitUrl.trim() : '';
  const sepaFontUrl = typeof sepaConfig.fontUrl === 'string' ? sepaConfig.fontUrl.trim() : '';

  const toAbsoluteUrl = (relativePath) => {
    if (typeof relativePath !== 'string' || relativePath.trim() === '') {
      return '';
    }
    try {
      return new URL(relativePath, import.meta.url).href;
    } catch (error) {
      console.warn('[register] asset url resolution failed', { relativePath, error });
      return '';
    }
  };

  const uniqueNonEmptyStrings = (values) => {
    const seen = new Set();
    return values.reduce((acc, value) => {
      if (typeof value !== 'string') {
        return acc;
      }
      const trimmed = value.trim();
      if (trimmed === '' || seen.has(trimmed)) {
        return acc;
      }
      seen.add(trimmed);
      acc.push(trimmed);
      return acc;
    }, []);
  };

  const getRestRoot = () => {
    const base = (getRegisterConfig().rest && getRegisterConfig().rest.root)
      || `${window.location.origin}/wp-json/go/public/v1/`;
    return base.replace(/\/?$/, '/');
  };

  const buildRestUrl = (path) => `${getRestRoot()}${path.replace(/^\//, '')}`;

  const debounce = (fn, delay) => {
    let timeout;
    return (...args) => {
      window.clearTimeout(timeout);
      timeout = window.setTimeout(() => fn.apply(null, args), delay);
    };
  };

  const sepaMandateState = {
    currentPromise: null,
    blob: null,
    filename: '',
    reference: '',
    snapshot: null,
    templateBytes: null,
    fontBytes: null,
    fontkitRegistered: false,
    fontSource: '',
    generatedAt: undefined,
    lastError: '',
  };

  const cleanDigits = (value, maxLength) => value.replace(/\D/g, '').slice(0, maxLength);

  const formatIban = (value) => {
    const raw = value.replace(/\s+/g, '').toUpperCase();
    return raw.replace(/(.{4})/g, '$1 ').trim();
  };

  const maskIban = (value) => {
    const sanitized = (value || '').replace(/\s+/g, '').toUpperCase();
    if (!sanitized) {
      return '';
    }
    const groups = sanitized.match(/.{1,4}/g) || [];
    return groups
      .map((group, index) => {
        if (index <= 1 || index === groups.length - 1) {
          return group;
        }
        return '****';
      })
      .join(' ');
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
    const resendBtn = document.getElementById('resend-code-btn');
    const resendCountdown = document.getElementById('resend-countdown');
    const verificationMessage = document.getElementById('verification-message');
    const verificationSuccess = document.getElementById('verification-success');
    const verificationExpiry = document.getElementById('verification-expiry');
    const verificationCodeField = document.getElementById('verification_code');
    const verificationInputContainer = document.getElementById('verification-input');
    const verificationActions = document.getElementById('verification-actions');
    const restartBtn = document.getElementById('restart-register-btn');
    const registerError = document.getElementById('register-error');
    const termsContainer = document.getElementById('terms-container');
    const termsError = document.getElementById('terms-error');
    const termsCheckbox = document.getElementById('terms');
    const emailSent = document.getElementById('email-sent');
    const verificationText = document.getElementById('verification-text');
    const verificationInstructions = document.getElementById('verification-instructions');
    const verificationSuccessText = document.getElementById('verification-success-text');
    const verificationSuccessMessage = document.getElementById('verification-success-message');
    const DEFAULT_VERIFICATION_SUCCESS = (verificationSuccessText && verificationSuccessText.textContent.trim())
      || (verificationSuccessMessage && verificationSuccessMessage.textContent.trim())
      || 'Cuenta verificada. Ya puedes acceder a tu área de usuario.';

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
    const sepaIbanToggle = document.getElementById('summary-sepa-iban-toggle');
    const sepaCountryField = document.getElementById('sepa_country');

    const hasWorkshopField = document.getElementById('has_workshop');
    const hasWebField = document.getElementById('has_web');
    const autoSignatureField = document.getElementById('auto_signature');
    const enableSepaField = document.getElementById('enable_sepa');

    const workshopFields = {
      container: document.getElementById('workshop-fields'),
      name: document.getElementById('workshop_name'),
      fiscalName: document.getElementById('workshop_fiscal_name'),
      taxId: document.getElementById('workshop_tax_id'),
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
      tradeNameItem: document.getElementById('summary-trade-name-item'),
      tradeName: document.getElementById('summary-trade-name'),
      legalNameItem: document.getElementById('summary-legal-name-item'),
      legalName: document.getElementById('summary-legal-name'),
      name: document.getElementById('summary-name'),
      email: document.getElementById('summary-email'),
      phone: document.getElementById('summary-phone'),
      preferencesGroup: document.getElementById('summary-preferences'),
      webItem: document.getElementById('summary-web-item'),
      web: document.getElementById('summary-web'),
      signatureItem: document.getElementById('summary-signature-item'),
      signature: document.getElementById('summary-signature'),
      sepaStatusItem: document.getElementById('summary-sepa-status-item'),
      sepaStatus: document.getElementById('summary-sepa-status'),
      workshopGroup: document.getElementById('summary-workshop'),
      workshopName: document.getElementById('summary-workshop-name'),
      workshopFiscalName: document.getElementById('summary-workshop-fiscal-name'),
      workshopTaxId: document.getElementById('summary-workshop-tax-id'),
      workshopContact: document.getElementById('summary-workshop-contact'),
      workshopAddress: document.getElementById('summary-workshop-address'),
      workshopPhone: document.getElementById('summary-workshop-phone'),
      workshopEmail: document.getElementById('summary-workshop-email'),
      sepaGroup: document.getElementById('summary-sepa'),
      sepaName: document.getElementById('summary-sepa-name'),
      sepaAddress: document.getElementById('summary-sepa-address'),
      sepaSwift: document.getElementById('summary-sepa-swift'),
      sepaIban: document.getElementById('summary-sepa-iban'),
    };

    const state = {
      currentStep: 1,
      stepSequence: STEP_SEQUENCE_DEFAULT.slice(),
      totalSteps: STEP_SEQUENCE_DEFAULT.length,
      selectedChannel: null,
      emailStatus: 'empty',
      emailValue: '',
      emailCheckController: null,
      lastCheckedEmail: '',
      sepaEdited: new Set(),
      sepaMandateStatus: 'idle',
      sepaReference: '',
      skipAutoFocus: false,
      verification: {
        token: '',
        email: '',
        expiresAt: '',
        resendAvailableAt: 0,
      },
      verifyLockedUntil: 0,
    };

    const sepaConfigAvailable = sepaTemplateUrl !== '';

    const getSepaPaymentType = () => {
      const raw = typeof sepaCreditor.payment_type === 'string'
        ? sepaCreditor.payment_type.toLowerCase()
        : '';
      return raw && (raw === 'recurrente' || raw === 'unico') ? raw : 'recurrente';
    };

    const shouldGenerateSepa = () => {
      if (!sepaConfigAvailable) {
        return false;
      }
      if (!enableSepaField || !enableSepaField.checked) {
        return false;
      }
      if (state.selectedChannel === INDIVIDUAL_CHANNEL) {
        return false;
      }
      return true;
    };

    const buildSepaFilename = (reference) => {
      const safe = (reference || '')
        .toString()
        .replace(/[^A-Za-z0-9-]/g, '')
        .toLowerCase();
      return safe ? `mandato-sepa-${safe}.pdf` : 'mandato-sepa.pdf';
    };

    const generateReferenceValue = () => {
      const timestamp = Date.now().toString(36).toUpperCase();
      const random = Math.random().toString(36).slice(2, 8).toUpperCase();
      const raw = `${sepaReferencePrefix}-${timestamp}-${random}`
        .replace(/[^A-Z0-9-]/g, '')
        .slice(0, 40);
      return raw || `${sepaReferencePrefix}-${timestamp}`;
    };

    const ensureSepaReference = () => {
      if (!state.sepaReference) {
        state.sepaReference = generateReferenceValue();
      }
      return state.sepaReference;
    };

    const normalizeIbanValue = (value) => (value || '').replace(/\s+/g, '').toUpperCase();

    const getSepaSnapshot = () => ({
      name: getValue('sepa_name'),
      address: getValue('sepa_address'),
      postalCode: getValue('sepa_postal_code'),
      city: getValue('sepa_city'),
      state: getValue('sepa_state'),
      country: sepaCountryField ? sepaCountryField.value.trim() : '',
      swift: sepaSwiftField ? sepaSwiftField.value.trim().toUpperCase() : '',
      iban: normalizeIbanValue(sepaIbanField ? sepaIbanField.value : ''),
    });

    const isStepAvailable = (stepNumber) => state.stepSequence.includes(stepNumber);
    const getStepNumberAtPosition = (position) => {
      if (!state.stepSequence.length) {
        return 1;
      }
      const index = Math.min(Math.max(position - 1, 0), state.stepSequence.length - 1);
      return state.stepSequence[index];
    };
    const getCurrentStepNumber = () => getStepNumberAtPosition(state.currentStep);
    const setStepSequence = (sequence) => {
      const nextSequence = Array.isArray(sequence) && sequence.length ? sequence : STEP_SEQUENCE_DEFAULT;
      state.stepSequence = nextSequence.slice();
      state.totalSteps = state.stepSequence.length;
      if (state.currentStep > state.totalSteps) {
        state.currentStep = state.totalSteps;
      }
      if (state.currentStep < 1) {
        state.currentStep = 1;
      }
      showCurrentStep();
      updateProgress();
    };
    let resetStep2Fields = () => {};

    let resendTimer = null;
    let verifyUnlockTimer = null;
    const setButtonDisabled = (button, disabled) => {
      if (!button) return;
      button.disabled = disabled;
      button.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    };

    const setRegisterError = (message) => {
      if (!registerError) return;
      if (message) {
        registerError.textContent = message;
        registerError.hidden = false;
      } else {
        registerError.textContent = '';
        registerError.hidden = true;
      }
    };

    const showTermsError = () => {
      if (!termsContainer || !termsError) return;
      termsContainer.classList.add('error');
      termsError.hidden = false;
    };

    const clearTermsError = () => {
      if (!termsContainer || !termsError) return;
      termsContainer.classList.remove('error');
      termsError.hidden = true;
    };

    const showVerificationMessage = (message, type = 'info') => {
      if (!verificationMessage) {
        return;
      }
      if (!message) {
        verificationMessage.textContent = '';
        verificationMessage.hidden = true;
        verificationMessage.className = 'verification-message';
        return;
      }
      verificationMessage.textContent = message;
      verificationMessage.className = `verification-message verification-message--${type}`;
      verificationMessage.hidden = false;
    };

    const updateVerificationExpiry = () => {
      if (!verificationExpiry) {
        return;
      }
      const defaultMessage = 'Caduca en 5 minutos.';
      if (!state.verification.expiresAt) {
        verificationExpiry.textContent = defaultMessage;
        return;
      }
      const expiryDate = new Date(state.verification.expiresAt);
      if (Number.isNaN(expiryDate.getTime())) {
        verificationExpiry.textContent = defaultMessage;
        return;
      }
      const timeRemaining = expiryDate.getTime() - Date.now();
      if (timeRemaining <= 0) {
        verificationExpiry.textContent = 'El código actual ha caducado.';
        return;
      }
      const minutesRemaining = Math.ceil(timeRemaining / 60000);
      if (minutesRemaining <= 60) {
        verificationExpiry.textContent = minutesRemaining === 1
          ? 'Caduca en 1 minuto.'
          : `Caduca en ${minutesRemaining} minutos.`;
        return;
      }
      const formatter = new Intl.DateTimeFormat('es-ES', {
        dateStyle: 'short',
        timeStyle: 'short',
      });
      verificationExpiry.textContent = `Caduca el ${formatter.format(expiryDate)}.`;
    };

    const persistVerificationState = () => {
      try {
        if (!state.verification.token) {
          window.sessionStorage.removeItem(VERIFICATION_STORAGE_KEY);
          return;
        }
        window.sessionStorage.setItem(
          VERIFICATION_STORAGE_KEY,
          JSON.stringify({
            token: state.verification.token,
            email: state.verification.email,
            expiresAt: state.verification.expiresAt,
            resendAvailableAt: state.verification.resendAvailableAt,
          })
        );
      } catch (error) {
        console.error('[register] persistVerificationState', error);
      }
    };

    const clearVerificationState = () => {
      state.verification = {
        token: '',
        email: '',
        expiresAt: '',
        resendAvailableAt: 0,
      };
      persistVerificationState();
    };

    const restoreVerificationState = () => {
      try {
        const raw = window.sessionStorage.getItem(VERIFICATION_STORAGE_KEY);
        if (!raw) {
          return false;
        }
        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object' || !parsed.token) {
          window.sessionStorage.removeItem(VERIFICATION_STORAGE_KEY);
          return false;
        }
        state.verification = {
          token: parsed.token,
          email: parsed.email || '',
          expiresAt: parsed.expiresAt || parsed.expires_at || '',
          resendAvailableAt: Number(parsed.resendAvailableAt || parsed.resend_available_at || 0),
        };
        return true;
      } catch (error) {
        console.error('[register] restoreVerificationState', error);
        return false;
      }
    };

    const stopResendCountdown = () => {
      if (resendTimer) {
        window.clearInterval(resendTimer);
        resendTimer = null;
      }
      if (resendCountdown) {
        resendCountdown.hidden = true;
        resendCountdown.textContent = '';
      }
      if (resendBtn) {
        setButtonDisabled(resendBtn, false);
      }
      state.verification.resendAvailableAt = 0;
      persistVerificationState();
    };

    const startResendCountdown = (seconds) => {
      if (!resendBtn || !resendCountdown) return;
      stopResendCountdown();
      if (!Number.isFinite(seconds) || seconds <= 0) {
        resendBtn.disabled = false;
        return;
      }
      let remaining = Math.max(0, Math.round(seconds));
      setButtonDisabled(resendBtn, true);
      resendCountdown.hidden = false;
      resendCountdown.textContent = `Podrás solicitar un nuevo código en ${remaining} segundos.`;
      state.verification.resendAvailableAt = Date.now() + remaining * 1000;
      persistVerificationState();
      resendTimer = window.setInterval(() => {
        remaining -= 1;
        if (remaining <= 0) {
          stopResendCountdown();
        } else {
          resendCountdown.textContent = `Podrás solicitar un nuevo código en ${remaining} segundos.`;
        }
      }, 1000);
    };

    const unlockVerifyButton = () => {
      if (verifyUnlockTimer) {
        window.clearTimeout(verifyUnlockTimer);
        verifyUnlockTimer = null;
      }
      state.verifyLockedUntil = 0;
      setButtonDisabled(verifyBtn, false);
    };

    const lockVerifyButton = (seconds) => {
      if (!verifyBtn) {
        return;
      }
      const duration = Math.max(0, Math.round(seconds));
      setButtonDisabled(verifyBtn, true);
      state.verifyLockedUntil = Date.now() + duration * 1000;
      if (verifyUnlockTimer) {
        window.clearTimeout(verifyUnlockTimer);
      }
      verifyUnlockTimer = window.setTimeout(() => {
        state.verifyLockedUntil = 0;
        setButtonDisabled(verifyBtn, false);
        verifyUnlockTimer = null;
      }, duration * 1000);
    };

    const applyVerificationState = (focusInput = false) => {
      if (emailSent && state.verification.email) {
        emailSent.textContent = state.verification.email;
      }
      updateVerificationExpiry();
      showVerificationMessage('');
      if (verificationText) {
        verificationText.setAttribute('data-state', 'instructions');
      }
      if (verificationInstructions) {
        verificationInstructions.hidden = false;
      }
      if (verificationSuccess) {
        verificationSuccess.hidden = true;
      }
      if (verificationSuccessText) {
        verificationSuccessText.hidden = true;
        verificationSuccessText.textContent = DEFAULT_VERIFICATION_SUCCESS;
      }
      if (verificationSuccessMessage) {
        verificationSuccessMessage.textContent = DEFAULT_VERIFICATION_SUCCESS;
      }
      if (verificationCodeField) {
        verificationCodeField.removeAttribute('disabled');
        verificationCodeField.value = '';
        clearFieldError(verificationCodeField);
        if (focusInput) {
          verificationCodeField.focus();
        }
      }
      if (verificationInputContainer) {
        verificationInputContainer.hidden = false;
      }
      if (verificationActions) {
        verificationActions.hidden = false;
      }
      unlockVerifyButton();
      if (state.verification.resendAvailableAt) {
        const remaining = Math.round((state.verification.resendAvailableAt - Date.now()) / 1000);
        if (remaining > 0) {
          startResendCountdown(remaining);
        } else {
          stopResendCountdown();
        }
      } else {
        stopResendCountdown();
      }
      persistVerificationState();
    };

    const showVerificationStep = () => {
      state.currentStep = 4;
      formSteps.forEach((step) => {
        step.classList.remove('active');
      });
      const step4 = document.getElementById('step-4');
      if (step4) {
        step4.classList.add('active');
      }
      if (progressContainer) {
        progressContainer.style.display = 'none';
      }
      applyVerificationState(true);
    };

    const completeVerification = (message = DEFAULT_VERIFICATION_SUCCESS) => {
      const successMessage = message && message.trim() ? message.trim() : DEFAULT_VERIFICATION_SUCCESS;
      if (verificationText) {
        verificationText.setAttribute('data-state', 'success');
      }
      if (verificationInstructions) {
        verificationInstructions.hidden = true;
      }
      if (verificationSuccessText) {
        verificationSuccessText.hidden = false;
        verificationSuccessText.textContent = successMessage;
      }
      if (verificationSuccessMessage) {
        verificationSuccessMessage.textContent = successMessage;
      }
      if (verificationSuccess) {
        verificationSuccess.hidden = false;
      }
      if (verificationInputContainer) {
        verificationInputContainer.hidden = true;
      }
      if (verificationActions) {
        verificationActions.hidden = true;
      }
      showVerificationMessage('');
      if (verificationCodeField) {
        verificationCodeField.value = '';
        verificationCodeField.setAttribute('disabled', 'disabled');
        clearFieldError(verificationCodeField);
      }
      stopResendCountdown();
      if (resendBtn) {
        setButtonDisabled(resendBtn, true);
      }
      setButtonDisabled(verifyBtn, true);
      state.verifyLockedUntil = 0;
      clearVerificationState();
    };

    const invalidateVerificationSession = (message) => {
      if (message) {
        showVerificationMessage(message, 'error');
      }
      stopResendCountdown();
      if (verificationCodeField) {
        verificationCodeField.value = '';
        verificationCodeField.setAttribute('disabled', 'disabled');
        clearFieldError(verificationCodeField);
      }
      state.verifyLockedUntil = 0;
      setButtonDisabled(verifyBtn, true);
      if (resendBtn) {
        setButtonDisabled(resendBtn, true);
      }
      clearVerificationState();
    };

    const restartRegistrationFlow = () => {
      stopResendCountdown();
      unlockVerifyButton();
      if (verificationActions) {
        verificationActions.hidden = false;
      }
      if (verificationInputContainer) {
        verificationInputContainer.hidden = false;
      }
      if (verificationSuccess) {
        verificationSuccess.hidden = true;
      }
      if (verificationSuccessText) {
        verificationSuccessText.hidden = true;
        verificationSuccessText.textContent = DEFAULT_VERIFICATION_SUCCESS;
      }
      if (verificationSuccessMessage) {
        verificationSuccessMessage.textContent = DEFAULT_VERIFICATION_SUCCESS;
      }
      if (verificationText) {
        verificationText.setAttribute('data-state', 'instructions');
      }
      if (verificationInstructions) {
        verificationInstructions.hidden = false;
      }
      if (verificationCodeField) {
        verificationCodeField.removeAttribute('disabled');
        verificationCodeField.value = '';
        clearFieldError(verificationCodeField);
      }
      showVerificationMessage('');
      if (verificationExpiry) {
        verificationExpiry.textContent = 'Caduca en 5 minutos.';
      }
      clearVerificationState();

      const form = document.getElementById('register-form');
      if (form) {
        form.reset();
      }

      document.querySelectorAll('.register-page .input-container').forEach((container) => {
        if (!container) {
          return;
        }
        container.classList.remove('error');
        const error = container.querySelector('.error-message');
        if (error) {
          error.remove();
        }
      });

      document.querySelectorAll('.register-page .checkbox-container').forEach((container) => {
        container.classList.remove('error');
      });

      setRegisterError('');
      clearChannelError();
      clearTermsError();

      if (termsCheckbox) {
        termsCheckbox.checked = false;
      }

      if (state.emailCheckController) {
        state.emailCheckController.abort();
        state.emailCheckController = null;
      }

      state.emailStatus = 'empty';
      state.emailValue = '';
      state.lastCheckedEmail = '';
      state.verifyLockedUntil = 0;
      state.sepaReference = '';
      state.sepaEdited.clear();
      resetSepaMandateState();
      setSepaStatus('idle');

      handleChannelSelection(null, { allowStepReset: false });
      resetStep2Fields();

      state.skipAutoFocus = true;
      goToStep(1);
      updateSummary();
      updateStep1ButtonState();
      updateStep2ButtonState();
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
        element.classList.add('error');
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
        element.classList.remove('error');
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
        const total = state.stepSequence.length;
        const progress = total > 1 ? ((state.currentStep - 1) / (total - 1)) * 100 : 0;
        progressBar.style.width = `${progress}%`;
      }

      steps.forEach((step) => {
        const stepNumber = Number(step.getAttribute('data-step'));
        const position = state.stepSequence.indexOf(stepNumber);
        const isVisible = position !== -1;
        step.classList.remove('active', 'completed');
        if (!isVisible) {
          step.style.display = 'none';
          step.setAttribute('aria-hidden', 'true');
          return;
        }
        step.style.display = '';
        step.setAttribute('aria-hidden', 'false');
        const stepPosition = position + 1;
        step.setAttribute('data-step-position', stepPosition);
        const indexElement = step.querySelector('.step-index');
        if (indexElement) {
          indexElement.textContent = String(stepPosition);
        }
        if (stepPosition < state.currentStep) {
          step.classList.add('completed');
        } else if (stepPosition === state.currentStep) {
          step.classList.add('active');
        }
      });
    };

    const showCurrentStep = () => {
      const activeNumber = getCurrentStepNumber();
      formSteps.forEach((step) => {
        const id = step.getAttribute('id') || '';
        const match = id.match(/^step-(\d+)/);
        if (!match) {
          return;
        }
        const stepNumber = Number(match[1]);
        if (stepNumber === 4) {
          if (!step.classList.contains('active')) {
            step.hidden = true;
            step.setAttribute('aria-hidden', 'true');
          }
          return;
        }
        const visible = isStepAvailable(stepNumber);
        if (!visible) {
          const activeElement = document.activeElement;
          if (activeElement && step.contains(activeElement) && typeof activeElement.blur === 'function') {
            activeElement.blur();
          }
          step.classList.remove('active');
          step.hidden = true;
          step.setAttribute('aria-hidden', 'true');
          return;
        }
        const isActive = stepNumber === activeNumber;
        if (!isActive) {
          const activeElement = document.activeElement;
          if (activeElement && step.contains(activeElement) && typeof activeElement.blur === 'function') {
            activeElement.blur();
          }
        }
        step.classList.toggle('active', isActive);
        step.hidden = !isActive;
        step.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });
      window.requestAnimationFrame(() => {
        if (state.skipAutoFocus) {
          state.skipAutoFocus = false;
          return;
        }
        const activeStep = document.querySelector('.form-step.active');
        if (!activeStep) {
          return;
        }
        if (activeStep.contains(document.activeElement)) {
          return;
        }
        const focusSelector = 'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled])';
        const focusTarget = activeStep.querySelector(focusSelector);
        if (focusTarget && typeof focusTarget.focus === 'function') {
          try {
            focusTarget.focus({ preventScroll: true });
          } catch (error) {
            focusTarget.focus();
          }
          return;
        }
        if (typeof activeStep.focus === 'function') {
          activeStep.setAttribute('tabindex', '-1');
          try {
            activeStep.focus({ preventScroll: true });
          } catch (error) {
            activeStep.focus();
          }
          activeStep.addEventListener('blur', () => {
            activeStep.removeAttribute('tabindex');
          }, { once: true });
        }
      });
    };

    const goToStep = (stepNumber) => {
      const total = state.stepSequence.length;
      state.currentStep = Math.max(1, Math.min(stepNumber, total));
      showCurrentStep();
      updateProgress();
      if (progressContainer) {
        progressContainer.style.display = '';
      }
      if (getCurrentStepNumber() === 3) {
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

    const setVisibility = (element, visible) => {
      if (!element) {
        return;
      }
      element.hidden = !visible;
      element.setAttribute('aria-hidden', visible ? 'false' : 'true');
      element.style.display = visible ? '' : 'none';
    };

    const handleEmailStatusChange = (status) => {
      const previousStatus = state.emailStatus;
      state.emailStatus = status;
      if (previousStatus !== status) {
        updateStep1ButtonState();
      }
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

      const endpoint = buildRestUrl(`register/check-email?email=${encodeURIComponent(value)}`);

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
      if (state.emailStatus === 'exists') {
        if (showError) {
          setFieldError(emailField, EMAIL_EXISTS_MESSAGE);
        }
      } else {
        clearFieldError(emailField);
      }
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
      if (workshopFields.fiscalName && !validateRequired(workshopFields.fiscalName, showError)) {
        valid = false;
      }
      if (workshopFields.taxId && !validateRequired(workshopFields.taxId, showError)) {
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
        if (showError && emailField) {
          setFieldError(emailField, EMAIL_EXISTS_MESSAGE);
        }
        valid = false;
      }
      if (state.emailStatus === 'pending') {
        valid = false;
      }
      return valid;
    };

    const validateStep2 = (showError) => {
      if (!isStepAvailable(2)) {
        return true;
      }
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
      if (!step2Next) {
        return;
      }
      if (!isStepAvailable(2)) {
        setButtonDisabled(step2Next, true);
        return;
      }
      const isValid = validateStep2(false);
      setButtonDisabled(step2Next, !isValid);
    };

    const markSepaEdited = (field) => {
      if (!field) return;
      state.sepaEdited.add(field.id);
      if (shouldGenerateSepa()) {
        invalidateSepaMandate();
      }
    };

    const prefillSepaFields = () => {
      if (!isStepAvailable(2)) {
        return;
      }
      const entries = [
        { target: 'sepa_name', get: combineName },
        { target: 'sepa_address', source: 'company_address' },
        { target: 'sepa_postal_code', source: 'company_postal_code' },
        { target: 'sepa_city', source: 'company_city' },
        { target: 'sepa_state', source: 'company_province' },
      ];
      let changed = false;
      entries.forEach(({ target, source, get }) => {
        const field = document.getElementById(target);
        if (!field || state.sepaEdited.has(field.id)) {
          return;
        }
        const value = typeof get === 'function' ? get() : getValue(source || '');
        if (value && !field.value) {
          field.value = value;
          changed = true;
        }
      });
      if (changed && shouldGenerateSepa()) {
        invalidateSepaMandate();
      }
    };

    const updateSummary = () => {
      const isIndividual = state.selectedChannel === INDIVIDUAL_CHANNEL;
      if (summary.channel) {
        summary.channel.textContent = CHANNEL_LABELS[state.selectedChannel] || '—';
      }

      setVisibility(summary.tradeNameItem, !isIndividual);
      if (summary.tradeName) {
        summary.tradeName.textContent = isIndividual ? '—' : (getValue('company_trade_name') || '—');
      }

      setVisibility(summary.legalNameItem, !isIndividual);
      if (summary.legalName) {
        summary.legalName.textContent = isIndividual ? '—' : (getValue('company_legal_name') || '—');
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

      const workshopActive = !isIndividual && !!(hasWorkshopField && hasWorkshopField.checked);
      setVisibility(summary.workshopGroup, workshopActive);
      if (workshopActive) {
        summary.workshopName.textContent = workshopFields.name?.value.trim() || '—';
        if (summary.workshopFiscalName) {
          summary.workshopFiscalName.textContent = workshopFields.fiscalName?.value.trim() || '—';
        }
        if (summary.workshopTaxId) {
          summary.workshopTaxId.textContent = workshopFields.taxId?.value.trim() || '—';
        }
        summary.workshopContact.textContent = workshopFields.contact?.value.trim() || '—';
        summary.workshopAddress.textContent = workshopFields.address?.value.trim() || '—';
        summary.workshopPhone.textContent = workshopFields.phone?.value.trim() || '—';
        summary.workshopEmail.textContent = workshopFields.email?.value.trim() || '—';
      } else {
        if (summary.workshopName) {
          summary.workshopName.textContent = '—';
        }
        if (summary.workshopFiscalName) {
          summary.workshopFiscalName.textContent = '—';
        }
        if (summary.workshopTaxId) {
          summary.workshopTaxId.textContent = '—';
        }
        if (summary.workshopContact) {
          summary.workshopContact.textContent = '—';
        }
        if (summary.workshopAddress) {
          summary.workshopAddress.textContent = '—';
        }
        if (summary.workshopPhone) {
          summary.workshopPhone.textContent = '—';
        }
        if (summary.workshopEmail) {
          summary.workshopEmail.textContent = '—';
        }
      }

      const webActive = !isIndividual && !!(hasWebField && hasWebField.checked);
      if (summary.web) {
        summary.web.textContent = '—';
      }
      setVisibility(summary.webItem, webActive);
      if (webActive && summary.web) {
        const urlInput = document.getElementById('web_url');
        const value = urlInput && urlInput.value.trim() ? urlInput.value.trim() : '—';
        summary.web.textContent = value;
      }

      const signatureActive = !isIndividual && !!(autoSignatureField && autoSignatureField.checked);
      if (summary.signature) {
        summary.signature.textContent = '—';
      }
      setVisibility(summary.signatureItem, signatureActive);
      if (signatureActive && summary.signature) {
        summary.signature.textContent = 'Activada';
      }

      const sepaActive = !isIndividual && !!(enableSepaField && enableSepaField.checked);
      if (summary.sepaStatus) {
        summary.sepaStatus.textContent = '—';
      }
      setVisibility(summary.sepaStatusItem, sepaActive);
      if (sepaActive && summary.sepaStatus) {
        let sepaStatusText = 'Solicitado';
        switch (state.sepaMandateStatus) {
          case 'generating':
            sepaStatusText = 'Generando mandato…';
            break;
          case 'ready':
            sepaStatusText = 'Mandato pendiente de firma';
            break;
          case 'error':
            sepaStatusText = 'Error al generar el mandato';
            break;
          case 'dirty':
            sepaStatusText = 'Pendiente de generar mandato';
            break;
          case 'idle':
          default:
            sepaStatusText = 'Solicitado';
            break;
        }
        summary.sepaStatus.textContent = sepaStatusText;
      } else if (summary.sepaStatus) {
        summary.sepaStatus.textContent = sepaConfigAvailable ? '—' : 'No disponible';
      }

      if (summary.preferencesGroup) {
        const preferencesVisible = !isIndividual && (webActive || signatureActive || sepaActive);
        setVisibility(summary.preferencesGroup, preferencesVisible);
      }

      setVisibility(summary.sepaGroup, sepaActive);
      if (sepaActive && summary.sepaGroup) {
        summary.sepaName.textContent = getValue('sepa_name') || '—';
        summary.sepaAddress.textContent = formatAddress(
          getValue('sepa_address'),
          getValue('sepa_postal_code'),
          getValue('sepa_city'),
          getValue('sepa_state')
        );
      if (summary.sepaSwift) {
        const swiftValue = sepaSwiftField ? sepaSwiftField.value.trim() : '';
        summary.sepaSwift.textContent = swiftValue || '—';
      }
      const ibanValue = sepaIbanField ? sepaIbanField.value.trim() : '';
      if (summary.sepaIban) {
        const previousFull = summary.sepaIban.dataset.fullValue || '';
        const formattedIban = ibanValue ? formatIban(ibanValue) : '';
        const maskedIban = ibanValue ? maskIban(ibanValue) : '';
        summary.sepaIban.dataset.fullValue = formattedIban;
        summary.sepaIban.dataset.maskedValue = maskedIban;
        if (sepaIbanToggle && formattedIban && formattedIban !== previousFull) {
          sepaIbanToggle.setAttribute('aria-pressed', 'false');
        }
        const isRevealed = sepaIbanToggle && sepaIbanToggle.getAttribute('aria-pressed') === 'true';
        summary.sepaIban.textContent = formattedIban
          ? (isRevealed ? formattedIban : (maskedIban || formattedIban))
          : '—';
        if (sepaIbanToggle) {
          const showLabel = sepaIbanToggle.dataset.labelShow || '';
          const hideLabel = sepaIbanToggle.dataset.labelHide || '';
          if (formattedIban) {
            sepaIbanToggle.hidden = false;
            sepaIbanToggle.setAttribute('aria-label', isRevealed ? hideLabel || showLabel : showLabel);
          } else {
            sepaIbanToggle.hidden = true;
            sepaIbanToggle.setAttribute('aria-pressed', 'false');
            if (showLabel) {
              sepaIbanToggle.setAttribute('aria-label', showLabel);
            }
          }
        }
      }
    } else if (summary.sepaGroup) {
      if (summary.sepaName) {
        summary.sepaName.textContent = '—';
      }
        if (summary.sepaAddress) {
          summary.sepaAddress.textContent = '—';
        }
      if (summary.sepaSwift) {
        summary.sepaSwift.textContent = '—';
      }
      if (summary.sepaIban) {
        summary.sepaIban.textContent = '—';
        summary.sepaIban.dataset.fullValue = '';
        summary.sepaIban.dataset.maskedValue = '';
      }
      if (sepaIbanToggle) {
        const showLabel = sepaIbanToggle.dataset.labelShow || '';
        sepaIbanToggle.hidden = true;
        sepaIbanToggle.setAttribute('aria-pressed', 'false');
        if (showLabel) {
          sepaIbanToggle.setAttribute('aria-label', showLabel);
        }
      }
    }
  };

    const setSepaStatus = (status) => {
      if (state.sepaMandateStatus !== status) {
        state.sepaMandateStatus = status;
        updateSummary();
      }
    };

    const resetSepaMandateState = () => {
      sepaMandateState.currentPromise = null;
      sepaMandateState.blob = null;
      sepaMandateState.filename = '';
      sepaMandateState.snapshot = null;
      sepaMandateState.reference = '';
      sepaMandateState.generatedAt = undefined;
      sepaMandateState.lastError = '';
    };

    const invalidateSepaMandate = () => {
      resetSepaMandateState();
      if (shouldGenerateSepa()) {
        setSepaStatus('dirty');
      } else {
        state.sepaReference = '';
        setSepaStatus('idle');
      }
    };

    const snapshotsAreEqual = (a, b) => {
      if (!a || !b) {
        return false;
      }
      const keys = ['name', 'address', 'postalCode', 'city', 'state', 'country', 'swift', 'iban'];
      return keys.every((key) => (a[key] || '') === (b[key] || ''));
    };

    const generateSepaMandate = async (snapshot) => {
      if (!sepaTemplateUrl) {
        throw new Error('sepa_template_missing');
      }
      if (typeof PDFLib === 'undefined' || !PDFLib.PDFDocument) {
        throw new Error('pdf_lib_unavailable');
      }

      const reference = ensureSepaReference();

      if (!sepaMandateState.templateBytes) {
        const templateResponse = await fetch(sepaTemplateUrl, { credentials: 'same-origin' });
        if (!templateResponse.ok) {
          throw new Error('sepa_template_fetch_failed');
        }
        sepaMandateState.templateBytes = await templateResponse.arrayBuffer();
      }

      const pdfDoc = await PDFLib.PDFDocument.load(sepaMandateState.templateBytes);

      const fontkitCandidates = uniqueNonEmptyStrings([
        sepaFontkitUrl,
        toAbsoluteUrl('./fontkit.umd.min.js'),
      ]);
      let fontkitLoaded = sepaMandateState.fontkitRegistered && typeof globalThis.fontkit !== 'undefined';

      if (!fontkitLoaded) {
        for (const candidate of fontkitCandidates) {
          try {
            await import(candidate);
            if (globalThis.fontkit) {
              sepaMandateState.fontkitRegistered = true;
              fontkitLoaded = true;
              break;
            }
          } catch (error) {
            console.warn('[register] fontkit load failed', { url: candidate, error });
          }
        }
      }

      if (fontkitLoaded && globalThis.fontkit) {
        try {
          pdfDoc.registerFontkit(globalThis.fontkit);
        } catch (error) {
          console.warn('[register] fontkit register failed', error);
        }
      }

      if (!sepaMandateState.fontBytes) {
        const fontCandidates = uniqueNonEmptyStrings([
          sepaFontUrl,
          toAbsoluteUrl('../fonts/RobotoMono-Regular.ttf'),
        ]);
        for (const candidate of fontCandidates) {
          try {
            const fontResponse = await fetch(candidate, { credentials: 'same-origin' });
            if (!fontResponse.ok) {
              throw new Error(`HTTP ${fontResponse.status}`);
            }
            sepaMandateState.fontBytes = await fontResponse.arrayBuffer();
            sepaMandateState.fontSource = candidate;
            break;
          } catch (error) {
            console.warn('[register] sepa font fetch failed', { url: candidate, error });
          }
        }
      }

      let activeFont = null;
      let appearanceFontName = '';

      if (sepaMandateState.fontBytes) {
        try {
          activeFont = await pdfDoc.embedFont(sepaMandateState.fontBytes);
          if (activeFont && typeof activeFont.name === 'string') {
            appearanceFontName = activeFont.name;
          }
        } catch (error) {
          console.warn('[register] sepa custom font embed failed', error);
        }
      }

      if (!activeFont) {
        try {
          const fallbackName = (PDFLib.StandardFonts && PDFLib.StandardFonts.Helvetica)
            ? PDFLib.StandardFonts.Helvetica
            : 'Helvetica';
          activeFont = await pdfDoc.embedStandardFont(fallbackName);
          if (activeFont && typeof activeFont.name === 'string') {
            appearanceFontName = activeFont.name;
          } else {
            appearanceFontName = typeof fallbackName === 'string' ? fallbackName : 'Helvetica';
          }
        } catch (error) {
          console.error('[register] sepa fallback font embed failed', error);
          throw new Error('sepa_font_embed_failed');
        }
      }

      const resolvedFontName = appearanceFontName || 'Helvetica';
      const form = pdfDoc.getForm();

      if (form && PDFLib?.PDFName && PDFLib?.PDFBool && typeof pdfDoc.catalog?.lookup === 'function') {
        try {
          const acroForm = pdfDoc.catalog.lookup(PDFLib.PDFName.of('AcroForm'));
          if (acroForm && typeof acroForm.set === 'function') {
            acroForm.set(PDFLib.PDFName.of('NeedAppearances'), PDFLib.PDFBool.True);
          }
        } catch (error) {
          console.warn('[register] Unable to mark AcroForm for appearances', error);
        }
      }

      const creditorCountry = typeof sepaCreditor.country === 'string' && sepaCreditor.country
        ? sepaCreditor.country
        : 'España';
      const creditorPostal = typeof sepaCreditor.postal_code === 'string' ? sepaCreditor.postal_code : '';
      const creditorCity = typeof sepaCreditor.city === 'string' ? sepaCreditor.city : '';
      const creditorProvince = typeof sepaCreditor.province === 'string' ? sepaCreditor.province : '';
      const debtorCountry = snapshot.country || creditorCountry;
      const signatureLocality = creditorProvince || snapshot.city || '';
      const signatureDate = new Date();
      const formattedSignatureDate = signatureDate.toLocaleDateString('es-ES');

      const fieldMap = {
        pdf_acreedor_referencia: reference,
        pdf_acreedor_id: sepaCreditor.id || '',
        pdf_acreedor_nombre: sepaCreditor.name || '',
        pdf_acreedor_direccion: sepaCreditor.address || '',
        pdf_acreedor_pais: creditorCountry,
        pdf_acreedor_cp: creditorPostal,
        pdf_acreedor_poblacion: creditorCity,
        pdf_acreedor_provincia: creditorProvince,
        pdf_deudor_nombre: snapshot.name,
        pdf_deudor_direccion: snapshot.address,
        pdf_deudor_pais: debtorCountry,
        pdf_deudor_cp: snapshot.postalCode,
        pdf_deudor_poblacion: snapshot.city,
        pdf_deudor_provincia: snapshot.state,
        pdf_deudor_swift: snapshot.swift,
        pdf_deudor_iban: formatIban(snapshot.iban || ''),
        pdf_deudor_firma_fecha: formattedSignatureDate,
        pdf_deudor_firma_localidad: signatureLocality,
      };

      const editableFields = new Set(['pdf_deudor_firma', 'pdf_deudor_firma_fecha', 'pdf_deudor_firma_localidad']);

      Object.entries(fieldMap).forEach(([name, value]) => {
        const stringValue = value === undefined || value === null ? '' : String(value);
        if (stringValue === '') {
          return;
        }
        try {
          const field = form.getTextField(name);
          const fontSize = 9;
          field.setText(stringValue);
          field.setFontSize(fontSize);
          if (field.acroField && typeof field.acroField.setDefaultAppearance === 'function') {
            const resolvedName = resolvedFontName || 'Helvetica';
            field.acroField.setDefaultAppearance(
              `0.5 0.5 0.5 rg /${resolvedName} ${fontSize} Tf`,
            );
          }
          if (typeof field.updateAppearances === 'function') {
            try {
              field.updateAppearances(activeFont);
            } catch (appearanceError) {
              console.warn('[register] Unable to refresh field appearance', appearanceError);
            }
          }
          if (!editableFields.has(name) && typeof field.enableReadOnly === 'function') {
            field.enableReadOnly();
          }
        } catch (error) {
          console.warn('[register] Missing PDF field', name, error);
        }
      });

      const paymentType = getSepaPaymentType();
      try {
        const recurrentField = form.getCheckBox('pdf_deudor_pago_recurrente');
        const uniqueField = form.getCheckBox('pdf_deudor_pago_unico');
        if (paymentType === 'unico') {
          uniqueField.check();
          recurrentField.uncheck();
        } else {
          recurrentField.check();
          uniqueField.uncheck();
        }
        if (typeof recurrentField.enableReadOnly === 'function') {
          recurrentField.enableReadOnly();
        }
        if (typeof uniqueField.enableReadOnly === 'function') {
          uniqueField.enableReadOnly();
        }
      } catch (error) {
        console.warn('[register] Unable to set payment checkbox', error);
      }

      if (form && typeof form.getSignature === 'function') {
        try {
          const signatureField = form.getSignature('pdf_deudor_firma');
          if (signatureField && typeof signatureField.disableReadOnly === 'function') {
            signatureField.disableReadOnly();
          }
          if (
            signatureField
            && signatureField.acroField
            && signatureField.acroField.dict
            && PDFLib?.PDFName
            && PDFLib?.PDFNumber
            && typeof signatureField.acroField.dict.set === 'function'
          ) {
            try {
              signatureField.acroField.dict.set(
                PDFLib.PDFName.of('Ff'),
                PDFLib.PDFNumber.of(0)
              );
              if (typeof signatureField.acroField.dict.delete === 'function') {
                signatureField.acroField.dict.delete(PDFLib.PDFName.of('V'));
              }
            } catch (innerError) {
              console.warn('[register] Unable to reset signature field flags', innerError);
            }
          }
        } catch (error) {
          console.warn('[register] Unable to keep signature field editable', error);
        }
      }

      if (form && typeof form.flatten === 'function') {
        try {
          form.flatten({
            updateFieldAppearances: true,
            exclude: Array.from(editableFields),
          });
        } catch (error) {
          console.warn('[register] Unable to flatten SEPA form', error);
        }
      }

      const generatedAt = signatureDate.toISOString();
      const filled = await pdfDoc.save({ updateFieldAppearances: true });
      const blob = new Blob([filled], { type: 'application/pdf' });
      const filename = buildSepaFilename(reference);

      return { blob, filename, reference, generatedAt };
    };

    const ensureSepaMandateReady = async ({ showErrors = false } = {}) => {
      if (!shouldGenerateSepa()) {
        state.sepaReference = '';
        resetSepaMandateState();
        setSepaStatus('idle');
        return null;
      }

      const snapshot = getSepaSnapshot();

      if (
        sepaMandateState.blob
        && sepaMandateState.snapshot
        && snapshotsAreEqual(sepaMandateState.snapshot, snapshot)
      ) {
        setSepaStatus('ready');
        const reference = state.sepaReference || sepaMandateState.reference || ensureSepaReference();
        return {
          blob: sepaMandateState.blob,
          filename: sepaMandateState.filename || buildSepaFilename(reference),
          reference,
          generatedAt: sepaMandateState.generatedAt || new Date().toISOString(),
        };
      }

      if (!sepaMandateState.currentPromise) {
        setSepaStatus('generating');
        sepaMandateState.currentPromise = generateSepaMandate(snapshot);
      }

      try {
        const result = await sepaMandateState.currentPromise;
        sepaMandateState.currentPromise = null;
        sepaMandateState.blob = result.blob;
        sepaMandateState.filename = result.filename;
        sepaMandateState.snapshot = snapshot;
        sepaMandateState.reference = result.reference;
        sepaMandateState.generatedAt = result.generatedAt;
        sepaMandateState.lastError = '';
        state.sepaReference = result.reference;
        setSepaStatus('ready');
        return result;
      } catch (error) {
        console.error('[register] sepa mandate error', error);
        sepaMandateState.currentPromise = null;
        sepaMandateState.blob = null;
        sepaMandateState.snapshot = null;
        sepaMandateState.reference = '';
        sepaMandateState.generatedAt = undefined;
        sepaMandateState.lastError = error && error.message ? error.message : 'unknown_error';
        setSepaStatus('error');
        if (showErrors) {
          setRegisterError('No se ha podido generar el mandato SEPA. Revisa los datos e inténtalo de nuevo.');
        }
        return null;
      }
    };

    const handleChannelSelection = (channel, { allowStepReset = true } = {}) => {
      const normalizedChannel = typeof channel === 'string' && channel !== '' ? channel : null;
      const previousChannel = state.selectedChannel;
      const channelChanged = previousChannel !== normalizedChannel;

      state.selectedChannel = normalizedChannel;

      if (channelChanged) {
        state.skipAutoFocus = true;
      }
      channelButtons.forEach((button) => {
        const buttonChannel = button.getAttribute('data-channel');
        const isActive = normalizedChannel !== null && buttonChannel === normalizedChannel;
        button.classList.toggle('active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });
      if (companySection) {
        const showCompany = normalizedChannel !== null && PROFESSIONAL_CHANNELS.has(normalizedChannel);
        companySection.hidden = !showCompany;
        companySection.setAttribute('aria-hidden', showCompany ? 'false' : 'true');
      }
      const isIndividual = normalizedChannel === INDIVIDUAL_CHANNEL;
      setStepSequence(isIndividual ? STEP_SEQUENCE_INDIVIDUAL : STEP_SEQUENCE_DEFAULT);
      if (isIndividual) {
        resetStep2Fields();
      }
      if (channelChanged && previousChannel) {
        state.sepaEdited.clear();
        invalidateSepaMandate();
      }
      if (channelChanged && allowStepReset && state.currentStep !== 1) {
        state.currentStep = 1;
        goToStep(1);
      }
      if (!normalizedChannel) {
        updateSummary();
        updateStep1ButtonState();
        updateStep2ButtonState();
        clearChannelError();
        return;
      }
      clearChannelError();
      updateStep1ButtonState();
      updateStep2ButtonState();
      updateSummary();
      if (!isIndividual && enableSepaField && enableSepaField.checked) {
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
          if (checkbox === enableSepaField) {
            invalidateSepaMandate();
          }
        } else if (checkbox === enableSepaField) {
          invalidateSepaMandate();
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
      if (isStepAvailable(2)) {
        prefillSepaFields();
      }
      goToStep(2);
    };

    const step2Handler = async () => {
      if (!isStepAvailable(2)) {
        updateSummary();
        const position = state.stepSequence.indexOf(3);
        const nextStep = position >= 0 ? position + 1 : state.stepSequence.length;
        goToStep(nextStep);
        return;
      }
      if (!validateStep2(true)) {
        return;
      }
      updateSummary();
      if (shouldGenerateSepa()) {
        const mandate = await ensureSepaMandateReady({ showErrors: true });
        if (!mandate) {
          return;
        }
      }
      const position = state.stepSequence.indexOf(3);
      const nextStep = position >= 0 ? position + 1 : state.stepSequence.length;
      goToStep(nextStep);
    };

    const buildRegistrationPayload = () => {
      const formData = new window.FormData();
      const nonceField = document.getElementById('go360_register_nonce');
      const passwordField = document.getElementById('password');
      const confirmField = document.getElementById('confirm_password');

      if (nonceField) {
        formData.append('go360_register_nonce', nonceField.value);
      }

      const isIndividual = state.selectedChannel === INDIVIDUAL_CHANNEL;
      formData.append('channel', state.selectedChannel || '');
      formData.append('first_name', getValue('first_name'));
      formData.append('last_name', getValue('last_name'));
      formData.append('phone', phoneField ? phoneField.value.trim() : '');
      formData.append('email', emailField ? emailField.value.trim() : '');
      formData.append('password', passwordField ? passwordField.value : '');
      formData.append('confirm_password', confirmField ? confirmField.value : '');
      formData.append('terms', termsCheckbox && termsCheckbox.checked ? '1' : '0');

      formData.append('company_trade_name', isIndividual ? '' : getValue('company_trade_name'));
      formData.append('company_legal_name', isIndividual ? '' : getValue('company_legal_name'));
      formData.append('company_cif', isIndividual ? '' : getValue('company_cif'));
      formData.append('company_address', isIndividual ? '' : getValue('company_address'));
      formData.append('company_postal_code', isIndividual ? '' : getValue('company_postal_code'));
      formData.append('company_city', isIndividual ? '' : getValue('company_city'));
      formData.append('company_province', isIndividual ? '' : getValue('company_province'));

      const hasWorkshop = !isIndividual && hasWorkshopField && hasWorkshopField.checked;
      formData.append('has_workshop', hasWorkshop ? '1' : '0');
      formData.append('workshop_name', hasWorkshop ? getValue('workshop_name') : '');
      formData.append('workshop_address', hasWorkshop ? getValue('workshop_address') : '');
      formData.append('workshop_fiscal_name', hasWorkshop ? getValue('workshop_fiscal_name') : '');
      formData.append('workshop_tax_id', hasWorkshop ? getValue('workshop_tax_id').toUpperCase() : '');
      formData.append('workshop_contact', hasWorkshop ? getValue('workshop_contact') : '');
      formData.append('workshop_phone', hasWorkshop ? getValue('workshop_phone') : '');
      formData.append('workshop_email', hasWorkshop ? getValue('workshop_email') : '');

      const hasWeb = !isIndividual && hasWebField && hasWebField.checked;
      formData.append('has_web', hasWeb ? '1' : '0');
      formData.append('web_url', hasWeb ? getValue('web_url') : '');

      const autoSignature = !isIndividual && autoSignatureField && autoSignatureField.checked;
      const enableSepa = !isIndividual && enableSepaField && enableSepaField.checked;

      formData.append('auto_signature', autoSignature ? '1' : '0');
      formData.append('enable_sepa', enableSepa ? '1' : '0');

      formData.append('sepa_name', enableSepa ? getValue('sepa_name') : '');
      formData.append('sepa_address', enableSepa ? getValue('sepa_address') : '');
      formData.append('sepa_postal_code', enableSepa ? getValue('sepa_postal_code') : '');
      formData.append('sepa_city', enableSepa ? getValue('sepa_city') : '');
      formData.append('sepa_state', enableSepa ? getValue('sepa_state') : '');
      formData.append('sepa_country', enableSepa ? getValue('sepa_country') : '');
      formData.append('sepa_swift', enableSepa ? getValue('sepa_swift') : '');
      formData.append('sepa_iban', enableSepa && sepaIbanField ? sepaIbanField.value.trim() : '');

      if (!isIndividual && avatarInput && avatarInput.files && avatarInput.files[0]) {
        formData.append('avatar', avatarInput.files[0]);
      }
      if (!isIndividual && signatureInput && signatureInput.files && signatureInput.files[0]) {
        formData.append('signature', signatureInput.files[0]);
      }
      if (!isIndividual && stampInput && stampInput.files && stampInput.files[0]) {
        formData.append('stamp', stampInput.files[0]);
      }

      return formData;
    };

    const handleRegister = async () => {
      setRegisterError('');

      const step1Valid = validateStep1(true);
      const step2Valid = isStepAvailable(2) ? validateStep2(true) : true;

      if (!step1Valid) {
        goToStep(1);
        return;
      }

      if (!step2Valid) {
        const position = state.stepSequence.indexOf(2);
        if (position >= 0) {
          goToStep(position + 1);
        }
        return;
      }

      if (!termsCheckbox || !termsCheckbox.checked) {
        showTermsError();
        if (termsCheckbox) {
          termsCheckbox.focus();
        }
        return;
      }

      clearTermsError();

      if (!registerBtn) {
        return;
      }

      setButtonDisabled(registerBtn, true);
      registerBtn.classList.add('is-loading');

      try {
        const formData = buildRegistrationPayload();
        let sepaPayload = null;
        if (shouldGenerateSepa()) {
          sepaPayload = await ensureSepaMandateReady({ showErrors: true });
          if (!sepaPayload) {
            registerBtn.classList.remove('is-loading');
            setButtonDisabled(registerBtn, false);
            return;
          }
          formData.append('sepa_document', sepaPayload.blob, sepaPayload.filename);
          formData.append('sepa_reference', sepaPayload.reference);
          formData.append('sepa_generated_at', sepaPayload.generatedAt || new Date().toISOString());
        } else {
          formData.append('sepa_reference', '');
        }
        setRegisterError('');
        const response = await window.fetch(buildRestUrl('register'), {
          method: 'POST',
          body: formData,
          credentials: 'same-origin',
        });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok || !payload || payload.status !== 'pending_verification') {
          const error = payload && payload.message ? payload.message : 'No se ha podido completar el registro. Inténtalo de nuevo.';
          if (payload && payload.code === 'go_register_email_exists' && emailField) {
            setFieldError(emailField, EMAIL_EXISTS_MESSAGE);
            state.emailStatus = 'exists';
            goToStep(1);
          } else {
            setRegisterError(error);
          }
          return;
        }

        state.verification = {
          token: payload.token || '',
          email: payload.email || (emailField ? emailField.value.trim() : ''),
          expiresAt: payload.expires_at || '',
          resendAvailableAt: payload.resend_available_in
            ? Date.now() + Number(payload.resend_available_in) * 1000
            : 0,
        };
        persistVerificationState();
        showVerificationStep();
      } catch (error) {
        console.error('[register] submit error', error);
        setRegisterError('No se ha podido completar el registro. Inténtalo de nuevo.');
      } finally {
        registerBtn.classList.remove('is-loading');
        setButtonDisabled(registerBtn, false);
      }
    };

    const handleVerify = async () => {
      if (!state.verification.token) {
        invalidateVerificationSession('No hemos encontrado ninguna solicitud pendiente de verificación. Inicia el registro de nuevo.');
        return;
      }

      if (!verificationCodeField) {
        return;
      }

      const code = verificationCodeField.value.trim();
      clearFieldError(verificationCodeField);
      if (!code) {
        setFieldError(verificationCodeField, REQUIRED_MESSAGE);
        verificationCodeField.focus();
        return;
      }

      showVerificationMessage('');

      if (state.verifyLockedUntil && Date.now() < state.verifyLockedUntil) {
        const remaining = Math.max(0, Math.round((state.verifyLockedUntil - Date.now()) / 1000));
        showVerificationMessage(`Has superado el número de intentos. Vuelve a intentarlo en ${remaining} segundos.`, 'error');
        lockVerifyButton(remaining);
        return;
      }

      setButtonDisabled(verifyBtn, true);
      if (verifyBtn) {
        verifyBtn.classList.add('is-loading');
      }

      try {
        const response = await window.fetch(buildRestUrl('register/verify'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            token: state.verification.token,
            code,
          }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          const message = payload && payload.message
            ? payload.message
            : 'No hemos podido verificar tu cuenta. Inténtalo de nuevo en unos segundos.';

          switch (payload && payload.code) {
            case 'go_verify_locked': {
              const retryRaw = payload && payload.data ? payload.data.retry_in : null;
              const retry = Number(retryRaw !== null && retryRaw !== undefined ? retryRaw : 60);
              lockVerifyButton(retry);
              showVerificationMessage(message, 'error');
              break;
            }
            case 'go_verify_mismatch':
              setFieldError(verificationCodeField, message);
              showVerificationMessage(message, 'error');
              verificationCodeField.focus();
              break;
            case 'go_verify_expired':
              state.verification.expiresAt = '';
              persistVerificationState();
              showVerificationMessage(message, 'error');
              break;
            case 'go_verify_missing':
              showVerificationMessage(message, 'error');
              break;
            case 'go_verify_unknown':
            case 'go_verify_invalid':
              invalidateVerificationSession(message);
              break;
            default:
              showVerificationMessage(message, 'error');
              break;
          }
          return;
        }

        clearFieldError(verificationCodeField);

        if (payload && payload.status === 'verified') {
          completeVerification(payload.message || 'Cuenta verificada correctamente.');
          return;
        }

        if (payload && payload.status === 'already_verified') {
          completeVerification(payload.message || 'Esta cuenta ya está verificada. Ya puedes iniciar sesión.');
          return;
        }

        completeVerification();
      } catch (error) {
        console.error('[register] verify error', error);
        showVerificationMessage('No hemos podido verificar tu cuenta. Inténtalo de nuevo en unos segundos.', 'error');
      } finally {
        if (verifyBtn) {
          verifyBtn.classList.remove('is-loading');
        }
        const locked = state.verifyLockedUntil && Date.now() < state.verifyLockedUntil;
        if (!locked && state.verification.token) {
          setButtonDisabled(verifyBtn, false);
        }
      }
    };

    const handleResend = async () => {
      if (!state.verification.token) {
        invalidateVerificationSession('No hemos encontrado ninguna solicitud pendiente de verificación. Inicia el registro de nuevo.');
        return;
      }

      showVerificationMessage('');
      if (resendBtn) {
        setButtonDisabled(resendBtn, true);
        resendBtn.classList.add('is-loading');
      }

      try {
        const response = await window.fetch(buildRestUrl('register/resend'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ token: state.verification.token }),
        });

        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          const message = payload && payload.message
            ? payload.message
            : 'No hemos podido reenviar el código. Inténtalo más tarde.';

          switch (payload && payload.code) {
            case 'go_resend_cooldown': {
              const retryRaw = payload && payload.data ? payload.data.retry_in : null;
              const retry = Number(retryRaw !== null && retryRaw !== undefined ? retryRaw : 60);
              startResendCountdown(retry);
              showVerificationMessage(message, 'info');
              break;
            }
            case 'go_resend_limit': {
              const retryRaw = payload && payload.data ? payload.data.retry_in : null;
              const retry = Number(retryRaw !== null && retryRaw !== undefined ? retryRaw : 0);
              if (retry > 0) {
                startResendCountdown(retry);
              }
              showVerificationMessage(message, 'error');
              break;
            }
            case 'go_resend_verified':
              completeVerification(payload.message || 'Esta cuenta ya está verificada. Ya puedes iniciar sesión.');
              break;
            case 'go_resend_unknown':
            case 'go_resend_invalid':
              invalidateVerificationSession(message);
              break;
            default:
              showVerificationMessage(message, 'error');
              break;
          }
          return;
        }

        state.verification.expiresAt = payload.expires_at || state.verification.expiresAt;
        if (payload && payload.resend_available_in) {
          state.verification.resendAvailableAt = Date.now() + Number(payload.resend_available_in) * 1000;
        } else {
          state.verification.resendAvailableAt = 0;
        }
        persistVerificationState();
        applyVerificationState(true);
        showVerificationMessage('Te hemos enviado un nuevo código de verificación.', 'success');
      } catch (error) {
        console.error('[register] resend error', error);
        showVerificationMessage('No hemos podido reenviar el código. Inténtalo de nuevo en unos minutos.', 'error');
      } finally {
        if (resendBtn) {
          resendBtn.classList.remove('is-loading');
          const remaining = state.verification.resendAvailableAt
            ? Math.round((state.verification.resendAvailableAt - Date.now()) / 1000)
            : 0;
          if (!resendTimer && remaining <= 0 && state.verification.token) {
            setButtonDisabled(resendBtn, false);
          }
        }
      }
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

    if (sepaIbanToggle) {
      const defaultLabel = sepaIbanToggle.dataset.labelShow || '';
      if (defaultLabel) {
        sepaIbanToggle.setAttribute('aria-label', defaultLabel);
      }
      sepaIbanToggle.addEventListener('click', () => {
        if (!summary.sepaIban) {
          return;
        }
        const fullValue = summary.sepaIban.dataset.fullValue || '';
        if (!fullValue) {
          return;
        }
        const maskedValue = summary.sepaIban.dataset.maskedValue || '';
        const isRevealed = sepaIbanToggle.getAttribute('aria-pressed') === 'true';
        const nextRevealed = !isRevealed;
        sepaIbanToggle.setAttribute('aria-pressed', nextRevealed ? 'true' : 'false');
        const showLabel = sepaIbanToggle.dataset.labelShow || '';
        const hideLabel = sepaIbanToggle.dataset.labelHide || '';
        sepaIbanToggle.setAttribute('aria-label', nextRevealed ? (hideLabel || showLabel) : showLabel);
        summary.sepaIban.textContent = nextRevealed
          ? fullValue
          : (maskedValue || fullValue);
      });
    }

    if (registerBtn) {
      registerBtn.addEventListener('click', handleRegister);
    }

    if (verifyBtn) {
      verifyBtn.addEventListener('click', handleVerify);
    }

    if (resendBtn) {
      resendBtn.addEventListener('click', handleResend);
    }

    if (termsCheckbox) {
      termsCheckbox.addEventListener('change', () => {
        if (termsCheckbox.checked) {
          clearTermsError();
        }
      });
    }

    if (verificationCodeField) {
      verificationCodeField.addEventListener('input', () => {
        verificationCodeField.value = cleanDigits(verificationCodeField.value, 6);
        clearFieldError(verificationCodeField);
      });
    }

    if (channelButtons.length) {
      channelButtons.forEach((button) => {
        button.setAttribute('role', 'button');
        button.setAttribute('tabindex', '0');
        button.setAttribute('aria-pressed', 'false');
        button.addEventListener('click', (event) => {
          event.preventDefault();
          const channel = button.getAttribute('data-channel') || '';
          const isActive = state.selectedChannel === channel;
          if (isActive) {
            handleChannelSelection(null);
            return;
          }
          handleChannelSelection(channel);
        });
        button.addEventListener('keydown', (event) => {
          if (event.key !== 'Enter' && event.key !== ' ') {
            return;
          }
          event.preventDefault();
          button.click();
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
          if (field.value.trim() === '') {
            return;
          }
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

    ['workshop_name', 'workshop_address', 'workshop_fiscal_name', 'workshop_tax_id', 'workshop_contact', 'workshop_email'].forEach((id) => {
      const field = document.getElementById(id);
      if (!field) return;
      field.addEventListener('input', () => {
        if (id === 'workshop_tax_id') {
          field.value = field.value.toUpperCase();
        }
        updateStep2ButtonState();
        updateSummary();
      });
      field.addEventListener('blur', () => {
        if (hasWorkshopField && hasWorkshopField.checked) {
          if (field.value.trim() === '') {
            return;
          }
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
          if (field.value.trim() === '') {
            return;
          }
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

    resetStep2Fields = () => {
      const uncheck = (checkbox) => {
        if (!checkbox) {
          return;
        }
        checkbox.checked = false;
        checkbox.dispatchEvent(new Event('change'));
      };

      uncheck(hasWorkshopField);
      uncheck(hasWebField);
      uncheck(autoSignatureField);
      uncheck(enableSepaField);

      const fieldsToClear = [
        'web_url',
        'workshop_name',
        'workshop_address',
        'workshop_fiscal_name',
        'workshop_tax_id',
        'workshop_contact',
        'workshop_phone',
        'workshop_email',
        'sepa_name',
        'sepa_address',
        'sepa_postal_code',
        'sepa_city',
        'sepa_state',
        'sepa_country',
        'sepa_swift',
        'sepa_iban',
      ];
      fieldsToClear.forEach((id) => {
        const field = document.getElementById(id);
        if (!field) {
          return;
        }
        if (field.tagName === 'INPUT' || field.tagName === 'TEXTAREA') {
          const defaultValue = Object.prototype.hasOwnProperty.call(field, 'defaultValue')
            ? field.defaultValue
            : '';
          field.value = defaultValue || '';
        }
        clearFieldError(field);
      });

      state.sepaEdited.clear();

      if (signatureInput) {
        signatureInput.value = '';
        signatureInput.dispatchEvent(new Event('change'));
      }
      if (stampInput) {
        stampInput.value = '';
        stampInput.dispatchEvent(new Event('change'));
      }
      if (signatureUpload) {
        clearUploadError(signatureUpload);
      }
      if (stampUpload) {
        clearUploadError(stampUpload);
      }

      const avatarFileInput = document.getElementById('avatar');
      if (avatarFileInput) {
        avatarFileInput.value = '';
        avatarFileInput.dispatchEvent(new Event('change'));
      }

      updateStep2ButtonState();
      updateSummary();
    };

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

    if (restartBtn) {
      restartBtn.addEventListener('click', () => {
        restartRegistrationFlow();
      });
    }

    const hasPendingVerification = restoreVerificationState();
    if (hasPendingVerification) {
      showVerificationStep();
    } else {
      showCurrentStep();
      updateProgress();
    }
    updateStep1ButtonState();
    updateStep2ButtonState();
    updateSummary();
  });
})();
