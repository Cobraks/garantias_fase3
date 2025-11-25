(() => {
  const config = window.__GO_VERIFY__ || {};
  const restRoot = (config.rest && config.rest.root) || `${window.location.origin}/wp-json/go/public/v1/`;
  const buildRestUrl = (path) => `${restRoot.replace(/\/?$/, '/')}${path.replace(/^\//, '')}`;

  const state = {
    token: '',
    email: typeof config.prefill_email === 'string' ? config.prefill_email : '',
    expiresAt: '',
    resendAvailableAt: 0,
  };

  document.addEventListener('DOMContentLoaded', () => {
    const emailField = document.getElementById('verification_email');
    const emailError = document.getElementById('verification-email-error');
    const emailContainer = document.getElementById('verification-email-container');
    const lookupBtn = document.getElementById('lookup-btn');
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
      if (!iso || !verificationExpiry) return;
      const date = new Date(iso);
      if (Number.isNaN(date.getTime())) {
        verificationExpiry.hidden = true;
        verificationExpiry.textContent = '';
        return;
      }
      const diff = date.getTime() - Date.now();
      if (diff <= 0) {
        verificationExpiry.hidden = false;
        verificationExpiry.textContent = 'El código ha caducado. Solicita uno nuevo para continuar.';
        return;
      }
      const minutes = Math.ceil(diff / 60000);
      verificationExpiry.hidden = false;
      if (minutes <= 60) {
        verificationExpiry.textContent = minutes === 1
          ? 'Caduca en 1 minuto.'
          : `Caduca en ${minutes} minutos.`;
        return;
      }
      const formatter = new Intl.DateTimeFormat('es-ES', { dateStyle: 'short', timeStyle: 'short' });
      verificationExpiry.textContent = `Caduca el ${formatter.format(date)}.`;
    };

    const applyContext = (payload) => {
      state.token = payload && payload.token ? String(payload.token) : '';
      state.email = payload && payload.email ? String(payload.email) : state.email;
      state.expiresAt = payload && payload.expires_at ? String(payload.expires_at) : '';
      const resendIn = Number(payload && payload.resend_available_in ? payload.resend_available_in : 0);
      state.resendAvailableAt = resendIn > 0 ? Date.now() + resendIn * 1000 : 0;

      if (emailTarget) {
        emailTarget.textContent = state.email || '—';
      }

      if (state.expiresAt) {
        formatExpiry(state.expiresAt);
      } else if (verificationExpiry) {
        verificationExpiry.hidden = true;
        verificationExpiry.textContent = '';
      }

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
      showGlobal('', 'info');
      showMessage(verificationMessage, '');
    };

    const handleLookup = async () => {
      if (!emailField) return;
      const email = emailField.value.trim();
      setFieldError(emailField, emailError, '');
      showGlobal('');
      showMessage(verificationMessage, '');
      if (email === '') {
        setFieldError(emailField, emailError, 'Introduce el correo electrónico.');
        emailField.focus();
        return;
      }

      setButtonState(lookupBtn, true);
      try {
        const url = new URL(buildRestUrl('register/verification'));
        url.searchParams.set('email', email);
        const response = await window.fetch(url.toString(), { credentials: 'same-origin' });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          const message = (payload && payload.message) || 'No hemos encontrado ninguna cuenta pendiente de verificación.';
          showGlobal(message, 'error');
          verificationBlock?.setAttribute('hidden', 'hidden');
          state.token = '';
          return;
        }
        applyContext(payload);
      } catch (error) {
        console.error('[verify] lookup error', error);
        showGlobal('No hemos podido recuperar la solicitud. Inténtalo de nuevo en unos segundos.', 'error');
      } finally {
        setButtonState(lookupBtn, false);
      }
    };

    const handleVerify = async () => {
      if (!state.token) {
        showGlobal('No hemos encontrado ninguna solicitud pendiente. Introduce tu correo para continuar.', 'error');
        return;
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
            case 'go_verify_locked': {
              const retry = payload && payload.data ? Number(payload.data.retry_in || 60) : 60;
              setButtonState(verifyBtn, false);
              setButtonState(resendBtn, false);
              showMessage(verificationMessage, message, 'error');
              return;
            }
            case 'go_verify_mismatch':
              setFieldError(codeField, codeError, message);
              showMessage(verificationMessage, message, 'error');
              codeField.focus();
              return;
            case 'go_verify_expired':
              showMessage(verificationMessage, message || 'Tu código ha caducado. Solicita uno nuevo.', 'error');
              if (verificationExpiry) {
                verificationExpiry.hidden = false;
                verificationExpiry.textContent = 'El código ha caducado. Solicita uno nuevo para continuar.';
              }
              if (verifyStepLabel) verifyStepLabel.setAttribute('data-state', 'expired');
              return;
            case 'go_verify_missing':
            case 'go_verify_unknown':
              showGlobal(message, 'error');
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
        }
        if (verifyStepLabel) {
          verifyStepLabel.setAttribute('data-state', 'verified');
        }
        if (verificationExpiry) {
          verificationExpiry.hidden = true;
          verificationExpiry.textContent = '';
        }
        showMessage(verificationMessage, payload && payload.message ? payload.message : 'Cuenta verificada correctamente.', 'success');
        if (verifyBtn) verifyBtn.disabled = true;
        if (resendBtn) resendBtn.disabled = true;
        if (codeField) {
          codeField.value = '';
          codeField.disabled = true;
        }
      } catch (error) {
        console.error('[verify] verify error', error);
        showMessage(verificationMessage, 'No hemos podido verificar tu cuenta. Inténtalo de nuevo.', 'error');
      } finally {
        setButtonState(verifyBtn, false);
      }
    };

    const handleResend = async () => {
      if (!state.token) {
        showGlobal('No hemos encontrado ninguna solicitud pendiente. Introduce tu correo para continuar.', 'error');
        return;
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
              startCountdown(retry);
              showMessage(verificationMessage, message, 'info');
              return;
            }
            case 'go_resend_limit': {
              const retry = payload && payload.data ? Number(payload.data.retry_in || 0) : 0;
              if (retry > 0) startCountdown(retry);
              showMessage(verificationMessage, message, 'error');
              return;
            }
            case 'go_resend_verified':
              showMessage(verificationMessage, payload && payload.message ? payload.message : message, 'success');
              if (verifySuccess) verifySuccess.hidden = false;
              return;
            default:
              showMessage(verificationMessage, message, 'error');
              return;
          }
        }
        const retry = payload && payload.resend_available_in ? Number(payload.resend_available_in) : 60;
        startCountdown(retry);
        if (payload && payload.expires_at) {
          formatExpiry(payload.expires_at);
        }
        if (verifyStepLabel) {
          verifyStepLabel.setAttribute('data-state', 'ready');
        }
        showMessage(verificationMessage, payload && payload.message ? payload.message : 'Hemos enviado un nuevo código.', 'success');
        if (verificationExpiry && verificationExpiry.textContent.includes('caducado')) {
          verificationExpiry.hidden = true;
          verificationExpiry.textContent = '';
        }
      } catch (error) {
        console.error('[verify] resend error', error);
        showMessage(verificationMessage, 'No hemos podido reenviar el código. Inténtalo más tarde.', 'error');
      } finally {
        setButtonState(resendBtn, false);
      }
    };

    if (lookupBtn) {
      lookupBtn.addEventListener('click', handleLookup);
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

    if (emailField && state.email) {
      emailField.value = state.email;
      handleLookup();
    }
  });
})();
