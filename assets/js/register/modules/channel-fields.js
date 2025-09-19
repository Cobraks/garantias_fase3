const parseChannels = (value = '') => value.split(',').map((item) => item.trim()).filter(Boolean);

export default class ChannelFields {
  constructor(form) {
    this.form = form;
    this.channelSelect = form.querySelector('[data-channel-select]');
    this.stepIntro = form.querySelector('[data-channel-empty]');
    this.sections = Array.from(form.querySelectorAll('[data-channel-section]'));
    this.toggleControls = Array.from(form.querySelectorAll('[data-toggle-control]'));

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
      const allowedChannels = parseChannels(section.dataset.channelSection);
      const shouldDisplay = allowedChannels.length === 0 || allowedChannels.includes(channel);
      section.classList.toggle('is-active', shouldDisplay && hasChannel);
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

    const inputs = Array.from(group.querySelectorAll('input, select, textarea'));
    inputs.forEach((input) => {
      if (control.dataset.toggleRequired === 'true') {
        input.required = isActive;
      }
    });
  }
}
