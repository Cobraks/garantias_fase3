(() => {
  const VERIFICATION_STORAGE_KEY = 'go360_register_verification';
  const EMAIL_REGEX = /^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
  const config = window.__GO_VERIFY__ || {};
  const restRoot = (config.rest && config.rest.root) || `${window.location.origin}/wp-json/go/public/v1/`;
  const buildRestUrl = (path) => `${restRoot.replace(/\/?$/, '/')}${path.replace(/^\//, '')}`;

  const state = {
    token: '',
    email: typeof config.prefill_email === 'string' ? config.prefill_email.trim() : '',
    expiresAt: '',
    resendAvailableAt: 0,
  };

  const readVerificationStorage = () => {
    const storages = [window.localStorage, window.sessionStorage];

    for (const storage of storages) {
      try {
        const raw = storage.getItem(VERIFICATION_STORAGE_KEY);
        if (!raw) {
          continue;
        }

        const parsed = JSON.parse(raw);
        if (!parsed || typeof parsed !== 'object') {
          continue;
        }

        const token = typeof parsed.token === 'string' ? parsed.token.trim() : '';
        const email = typeof parsed.email === 'string' ? parsed.email.trim() : '';
        const expiresAt = typeof parsed.expiresAt === 'string'
          ? parsed.expiresAt
          : (typeof parsed.expires_at === 'string' ? parsed.expires_at : '');
        const resendAvailableAt = Number(parsed.resendAvailableAt || parsed.resend_available_at || 0);

        if (!token && !email) {
          continue;
        }

        return {
          token,
          email,
          expiresAt,
          resendAvailableAt: Number.isFinite(resendAvailableAt) ? resendAvailableAt : 0,
        };
      } catch (error) {
        console.error('[verify] readVerificationStorage error', error);
      }
    }

    return null;
  };

  const persistVerificationStorage = () => {
    const payload = JSON.stringify({
      token: state.token,
      email: state.email,
      expiresAt: state.expiresAt,
      resendAvailableAt: state.resendAvailableAt,
    });

    [window.localStorage, window.sessionStorage].forEach((storage) => {
      try {
        if (!state.token && !state.email) {
          storage.removeItem(VERIFICATION_STORAGE_KEY);
          return;
        }
        storage.setItem(VERIFICATION_STORAGE_KEY, payload);
      } catch (error) {
        console.error('[verify] persistVerificationStorage error', error);
      }
    });
  };

  const clearVerificationStorage = () => {
    state.token = '';
    state.email = '';
    state.expiresAt = '';
    state.resendAvailableAt = 0;
    persistVerificationStorage();
  };

  document.addEventListener('DOMContentLoaded', () => {
    const verificationLookup = document.getElementById('verification-lookup');
    const lookupBtn = document.getElementById('lookup-code-btn');
    const emailField = document.getElementById('verification_email');
    const emailError = document.getElementById('verification-email-error');
    const verificationBlock = document.getElementById('verification-block');
    const verificationMessage = document.getElementById('verification-message');
    const verificationGlobal = document.getElementById('verification-global');
    const verificationExpiry = document.getElementById('verification-expiry');
    const resendCountdown = document.getElementById('resend-countdown');
    const emailTarget = document.getElementById('verification-email-target');
    const codeField = document.getElementById('verification_code');
    const codeError = document.getElementById('verification-code-error');
    const verifyBtn = document.getElementById('verify-btn');
    const resendBtn = document.getElementById('resend-code-btn');
    const verifySuccess = document.getElementById('verification-success');
    const verifyInstructions = document.getElementById('verification-instructions');
    const verifyStepLabel = document.getElementById('verify-step-label');
    const verifyStatusLabel = document.getElementById('verify-status-label');
    const verifyActions = document.getElementById('verification-actions');
    const verificationSuccessMessage = document.getElementById('verification-success-message');

    const storedContext = readVerificationStorage();
    if (storedContext) {
      state.token = storedContext.token;
      if (!state.email && storedContext.email) {
        state.email = storedContext.email;
      }
      state.expiresAt = storedContext.expiresAt;
      state.resendAvailableAt = storedContext.resendAvailableAt;
    }

    let countdownTimer = null;

    const setButtonState = (btn, loading) => {
      if (!btn) return;
      btn.classList.toggle('is-loading', loading);
      btn.disabled = Boolean(loading);
    };

    const setFieldError = (field, errorEl, message) => {
      if (!field || !errorEl) return;
      if (!message) {
        errorEl.hidden = true;
        errorEl.textContent = '';
        field.setAttribute('aria-invalid', 'false');
        field.parentElement?.classList.remove('is-error');
        return;
      }
      errorEl.textContent = message;
      errorEl.hidden = false;
      field.setAttribute('aria-invalid', 'true');
      field.parentElement?.classList.add('is-error');
    };

    const showMessage = (el, message, type = 'info') => {
      if (!el) return;
      el.textContent = message || '';
      el.className = `verification-message verification-message--${type}`;
      el.hidden = !message;
    };

    const showGlobal = (message, type = 'info') => {
      if (!verificationGlobal) return;
      verificationGlobal.textContent = message || '';
      verificationGlobal.className = `form-alert form-alert--${type}`;
      verificationGlobal.hidden = !message;
    };

    const updateLookupField = () => {
      if (emailField && state.email && emailField.value.trim() !== state.email) {
        emailField.value = state.email;
      }
    };

    const clearCountdown = () => {
      if (countdownTimer) {
        window.clearInterval(countdownTimer);
        countdownTimer = null;
      }
      if (resendCountdown) {
        resendCountdown.hidden = true;
        resendCountdown.textContent = '';
      }
    };

    const startCountdown = (seconds) => {
      if (!resendBtn || !resendCountdown) return;
      clearCountdown();
      const target = Date.now() + Math.max(0, Number(seconds || 0)) * 1000;
      const update = () => {
        const remaining = Math.round((target - Date.now()) / 1000);
        if (remaining <= 0) {
          clearCountdown();
          resendBtn.disabled = false;
          state.resendAvailableAt = 0;
          persistVerificationStorage();
          return;
        }
        resendBtn.disabled = true;
        resendCountdown.hidden = false;
        resendCountdown.textContent = `Podrás solicitar un nuevo código en ${remaining} segundos.`;
      };
      update();
      countdownTimer = window.setInterval(update, 1000);
    };

    const formatExpiry = (iso) => {
      if (!verificationExpiry) return;
      if (!iso) {
        verificationExpiry.hidden = true;
        verificationExpiry.textContent = '';
        return;
      }

      const date = new Date(iso);
      if (Number.isNaN(date.getTime())) {
        verificationExpiry.hidden = true;
        verificationExpiry.textContent = '';
        return;
      }

      const diff = date.getTime() - Date.now();
      verificationExpiry.hidden = false;
      if (diff <= 0) {
        verificationExpiry.textContent = 'El código ha caducado. Solicita uno nuevo para continuar.';
        return;
      }

      const minutes = Math.ceil(diff / 60000);
      if (minutes <= 60) {
        verificationExpiry.textContent = minutes === 1
          ? 'Caduca en 1 minuto.'
          : `Caduca en ${minutes} minutos.`;
        return;
      }

      const formatter = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' });
      verificationExpiry.textContent = `Caduca el ${formatter.format(date)}.`;
    };

    const showLookupPanel = () => {
      if (verificationLookup) {
        verificationLookup.hidden = false;
      }
    };

    const hideLookupPanel = () => {
      if (verificationLookup) {
        verificationLookup.hidden = false;
      }
    };

    const applyContext = (payload) => {
      state.token = payload && payload.token ? String(payload.token) : '';
      state.email = payload && payload.email ? String(payload.email) : state.email;
      state.expiresAt = payload && payload.expires_at ? String(payload.expires_at) : '';
      const resendIn = Number(payload && payload.resend_available_in ? payload.resend_available_in : 0);
      state.resendAvailableAt = resendIn > 0 ? Date.now() + resendIn * 1000 : 0;
      persistVerificationStorage();
      updateLookupField();
      hideLookupPanel();

      if (emailTarget) {
        emailTarget.textContent = state.email || '—';
      }

      formatExpiry(state.expiresAt);

      if (state.resendAvailableAt && resendBtn) {
        const remaining = Math.max(0, Math.round((state.resendAvailableAt - Date.now()) / 1000));
        if (remaining > 0) {
          startCountdown(remaining);
        } else {
          clearCountdown();
          resendBtn.disabled = false;
        }
      } else {
        clearCountdown();
        if (resendBtn) resendBtn.disabled = false;
      }

      if (verificationBlock) {
        verificationBlock.hidden = !state.token;
        verificationBlock.dataset.state = state.token ? 'ready' : 'idle';
      }
      if (codeField) {
        codeField.disabled = !state.token;
        if (state.token) {
          codeField.focus();
        }
      }
      if (verifyInstructions) {
        verifyInstructions.setAttribute('data-state', state.token ? 'ready' : 'idle');
      }
      if (verifyStepLabel) {
        verifyStepLabel.setAttribute('data-state', 'ready');
      }
      if (verifyStatusLabel) {
        verifyStatusLabel.textContent = state.email ? 'Código enviado a tu correo' : '';
      }
      if (verifyActions) {
        verifyActions.hidden = !state.token;
      }
      if (verifySuccess) {
        verifySuccess.hidden = true;
      }
      showGlobal('', 'info');
      showMessage(verificationMessage, '');
    };

    const showVerifiedState = (message) => {
      state.token = '';
      state.expiresAt = '';
      state.resendAvailableAt = 0;
      persistVerificationStorage();
      clearCountdown();

      if (verificationBlock) {
        verificationBlock.hidden = false;
        verificationBlock.dataset.state = 'verified';
      }
      hideLookupPanel();

      if (verifyStatusLabel) {
        verifyStatusLabel.textContent = 'Cuenta verificada';
      }
      if (verifyInstructions) {
        verifyInstructions.textContent = message || 'Esta cuenta ya está verificada. Inicia sesión para continuar.';
        verifyInstructions.setAttribute('data-state', 'verified');
      }
      if (verificationExpiry) {
        verificationExpiry.hidden = true;
        verificationExpiry.textContent = '';
      }
      if (verificationMessage) {
        verificationMessage.hidden = true;
        verificationMessage.textContent = '';
      }
      if (verificationSuccessMessage) {
        verificationSuccessMessage.textContent = message || 'Esta cuenta ya está verificada. Inicia sesión para continuar.';
      }
      if (verifyActions) {
        verifyActions.hidden = true;
      }
      if (verifySuccess) {
        verifySuccess.hidden = false;
      }
      if (codeField) {
        codeField.value = '';
        codeField.disabled = true;
      }
      if (resendBtn) resendBtn.disabled = true;
      if (verifyBtn) verifyBtn.disabled = true;
    };

    const resetPendingState = () => {
      state.token = '';
      state.expiresAt = '';
      state.resendAvailableAt = 0;
      persistVerificationStorage();
      clearCountdown();

      if (verificationBlock) {
        verificationBlock.hidden = true;
        verificationBlock.dataset.state = 'idle';
      }
      if (verifyActions) {
        verifyActions.hidden = false;
      }
      if (verifySuccess) {
        verifySuccess.hidden = true;
      }
      if (codeField) {
        codeField.value = '';
        codeField.disabled = true;
      }
      if (verificationExpiry) {
        verificationExpiry.hidden = true;
        verificationExpiry.textContent = '';
      }
      showLookupPanel();
    };

    const normalizeEmail = () => {
      const inputValue = emailField ? emailField.value.trim() : '';
      const email = inputValue || state.email;
      state.email = email.trim();
      updateLookupField();
      persistVerificationStorage();
      return state.email;
    };

    const handleLookup = async ({ focusCode = true, preserveGlobalError = false } = {}) => {
      const email = normalizeEmail();
      setFieldError(emailField, emailError, '');

      if (!preserveGlobalError) {
        showGlobal('');
      }
      showMessage(verificationMessage, '');

      if (email === '') {
        resetPendingState();
        setFieldError(emailField, emailError, 'Introduce el correo electrónico con el que te registraste.');
        showGlobal('Introduce tu correo electrónico para recuperar la verificación pendiente.', 'error');
        emailField?.focus();
        return;
      }

      if (!EMAIL_REGEX.test(email)) {
        resetPendingState();
        setFieldError(emailField, emailError, 'Introduce un correo electrónico válido.');
        showGlobal('El correo electrónico indicado no es válido.', 'error');
        emailField?.focus();
        return;
      }

      try {
        const url = new URL(buildRestUrl('register/verification'));
        url.searchParams.set('email', email);
        const response = await window.fetch(url.toString(), { credentials: 'same-origin' });
        const payload = await response.json().catch(() => ({}));

        if (!response.ok) {
          const message = (payload && payload.message) || 'No hemos encontrado ninguna cuenta pendiente de verificación.';
          const codeName = payload && payload.code ? String(payload.code) : '';

          if (codeName === 'go_verify_not_pending') {
            state.token = '';
            state.expiresAt = '';
            state.resendAvailableAt = 0;
            persistVerificationStorage();
            showVerifiedState(message);
            return;
          }

          resetPendingState();
          showGlobal(message, 'error');
          return;
        }

        applyContext(payload);
        if (!focusCode && codeField) {
          codeField.blur();
        }
      } catch (error) {
        console.error('[verify] lookup error', error);
        resetPendingState();
        showGlobal('No hemos podido recuperar la solicitud. Inténtalo de nuevo en unos segundos.', 'error');
      }
    };

    const handleVerify = async () => {
      if (!state.token) {
        await handleLookup({ focusCode: false, preserveGlobalError: true });
        if (!state.token) {
          showGlobal('No hemos encontrado ninguna solicitud pendiente. Introduce tu correo para recuperar o reenviar el código.', 'error');
          return;
        }
      }

      if (!codeField) return;
      const code = codeField.value.trim();
      setFieldError(codeField, codeError, '');
      showMessage(verificationMessage, '');
      showGlobal('');

      if (!code) {
        setFieldError(codeField, codeError, 'Introduce el código de verificación.');
        codeField.focus();
        return;
      }

      setButtonState(verifyBtn, true);
      try {
        const response = await window.fetch(buildRestUrl('register/verify'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ token: state.token, code }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          const message = (payload && payload.message) || 'No hemos podido verificar tu cuenta. Inténtalo de nuevo.';
          const codeName = payload && payload.code ? String(payload.code) : '';
          switch (codeName) {
            case 'go_verify_locked':
              showMessage(verificationMessage, message, 'error');
              return;
            case 'go_verify_mismatch':
              setFieldError(codeField, codeError, message);
              showMessage(verificationMessage, message, 'error');
              codeField.focus();
              return;
            case 'go_verify_expired':
              state.expiresAt = '';
              persistVerificationStorage();
              showMessage(verificationMessage, message || 'Tu código ha caducado. Solicita uno nuevo.', 'error');
              if (verificationExpiry) {
                verificationExpiry.hidden = false;
                verificationExpiry.textContent = 'El código ha caducado. Solicita uno nuevo para continuar.';
              }
              if (verifyStepLabel) verifyStepLabel.setAttribute('data-state', 'expired');
              return;
            case 'go_verify_missing':
            case 'go_verify_unknown':
              state.token = '';
              persistVerificationStorage();
              showGlobal(message, 'error');
              showLookupPanel();
              if (verificationBlock) {
                verificationBlock.hidden = true;
              }
              return;
            default:
              showMessage(verificationMessage, message, 'error');
              return;
          }
        }

        if (verifySuccess) {
          verifySuccess.hidden = false;
        }
        if (verificationBlock) {
          verificationBlock.dataset.state = 'verified';
          verificationBlock.hidden = false;
        }
        hideLookupPanel();
        if (verifyStepLabel) {
          verifyStepLabel.setAttribute('data-state', 'verified');
        }
        if (verifyStatusLabel) {
          verifyStatusLabel.textContent = 'Cuenta verificada';
        }
        if (verificationSuccessMessage && payload && payload.message) {
          verificationSuccessMessage.textContent = payload.message;
        }
        if (verificationExpiry) {
          verificationExpiry.hidden = true;
          verificationExpiry.textContent = '';
        }
        showMessage(verificationMessage, payload && payload.message ? payload.message : 'Cuenta verificada correctamente.', 'success');
        if (verifyBtn) verifyBtn.disabled = true;
        if (resendBtn) resendBtn.disabled = true;
        if (verifyActions) {
          verifyActions.hidden = true;
        }
        if (codeField) {
          codeField.value = '';
          codeField.disabled = true;
        }
        clearVerificationStorage();
      } catch (error) {
        console.error('[verify] verify error', error);
        showMessage(verificationMessage, 'No hemos podido verificar tu cuenta. Inténtalo de nuevo.', 'error');
      } finally {
        if (verifyBtn) {
          verifyBtn.classList.remove('is-loading');
          if (state.token) {
            verifyBtn.disabled = false;
          }
        }
      }
    };

    const handleResend = async () => {
      if (!state.token) {
        await handleLookup({ focusCode: false, preserveGlobalError: true });
        if (!state.token) {
          showGlobal('No hemos encontrado ninguna solicitud pendiente. Introduce tu correo para recuperar o reenviar el código.', 'error');
          return;
        }
      }

      showMessage(verificationMessage, '');
      showGlobal('');
      setButtonState(resendBtn, true);

      try {
        const response = await window.fetch(buildRestUrl('register/resend'), {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ token: state.token }),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          const message = (payload && payload.message) || 'No hemos podido reenviar el código. Inténtalo más tarde.';
          const codeName = payload && payload.code ? String(payload.code) : '';
          switch (codeName) {
            case 'go_resend_cooldown': {
              const retry = payload && payload.data ? Number(payload.data.retry_in || 60) : 60;
              state.resendAvailableAt = Date.now() + retry * 1000;
              persistVerificationStorage();
              startCountdown(retry);
              showMessage(verificationMessage, message, 'info');
              return;
            }
            case 'go_resend_limit': {
              const retry = payload && payload.data ? Number(payload.data.retry_in || 0) : 0;
              if (retry > 0) {
                state.resendAvailableAt = Date.now() + retry * 1000;
                persistVerificationStorage();
                startCountdown(retry);
              }
              showMessage(verificationMessage, message, 'error');
              return;
            }
            case 'go_resend_verified':
              clearVerificationStorage();
              showVerifiedState(payload && payload.message ? payload.message : message);
              return;
            case 'go_resend_unknown':
            case 'go_resend_invalid':
              state.token = '';
              persistVerificationStorage();
              showGlobal(message, 'error');
              showLookupPanel();
              if (verificationBlock) {
                verificationBlock.hidden = true;
              }
              return;
            default:
              showMessage(verificationMessage, message, 'error');
              return;
          }
        }

        const retry = payload && payload.resend_available_in ? Number(payload.resend_available_in) : 60;
        state.expiresAt = payload && payload.expires_at ? String(payload.expires_at) : state.expiresAt;
        state.resendAvailableAt = Date.now() + retry * 1000;
        persistVerificationStorage();
        startCountdown(retry);
        formatExpiry(state.expiresAt);
        if (verifyStepLabel) {
          verifyStepLabel.setAttribute('data-state', 'ready');
        }
        if (verifyStatusLabel) {
          verifyStatusLabel.textContent = 'Hemos enviado un nuevo código';
        }
        if (verificationBlock) {
          verificationBlock.hidden = false;
        }
        hideLookupPanel();
        if (codeField) {
          codeField.disabled = false;
          codeField.focus();
        }
        showMessage(verificationMessage, payload && payload.message ? payload.message : 'Hemos enviado un nuevo código.', 'success');
      } catch (error) {
        console.error('[verify] resend error', error);
        showMessage(verificationMessage, 'No hemos podido reenviar el código. Inténtalo más tarde.', 'error');
      } finally {
        if (resendBtn) {
          resendBtn.classList.remove('is-loading');
          const hasActiveCooldown = state.resendAvailableAt && state.resendAvailableAt > Date.now();
          resendBtn.disabled = Boolean(hasActiveCooldown || !state.token);
        }
      }
    };

    if (lookupBtn) {
      lookupBtn.addEventListener('click', async () => {
        setButtonState(lookupBtn, true);
        try {
          await handleLookup();
        } finally {
          setButtonState(lookupBtn, false);
        }
      });
    }

    if (emailField) {
      emailField.addEventListener('input', () => {
        setFieldError(emailField, emailError, '');
        state.email = emailField.value.trim();
        persistVerificationStorage();
      });

      emailField.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          lookupBtn?.click();
        }
      });
    }

    if (verifyBtn) {
      verifyBtn.addEventListener('click', handleVerify);
    }
    if (resendBtn) {
      resendBtn.addEventListener('click', handleResend);
    }

    if (codeField) {
      codeField.addEventListener('input', () => {
        codeField.value = codeField.value.replace(/\D/g, '').slice(0, 6);
        setFieldError(codeField, codeError, '');
      });
    }

    updateLookupField();

    if (state.email) {
      lookupBtn?.click();
    } else {
      resetPendingState();
      showGlobal('Introduce tu correo electrónico para recuperar la verificación pendiente o solicitar un nuevo código.', 'info');
    }
  });
})();
