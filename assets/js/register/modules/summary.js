const getFieldValue = (field) => {
  if (!field) {
    return '';
  }

  if (field.tagName === 'SELECT') {
    const option = field.options[field.selectedIndex];
    return option ? option.textContent.trim() : '';
  }

  if (field.type === 'checkbox') {
    return field.checked
      ? field.dataset.summaryOn ?? field.getAttribute('data-summary-label') ?? field.value
      : '';
  }

  if (field.type === 'file') {
    return field.files && field.files.length > 0 ? field.files[0].name : '';
  }

  return field.value.trim();
};

export default class Summary {
  constructor(form) {
    this.form = form;
    this.summaryMap = new Map();
    this.channelGroups = Array.from(form.querySelectorAll('[data-summary-channel]'));

    form.querySelectorAll('[data-summary-field]').forEach((node) => {
      this.summaryMap.set(node.dataset.summaryField, node);
    });

    this.handleInput = this.handleInput.bind(this);
    form.addEventListener('input', this.handleInput);
    form.addEventListener('change', this.handleInput);

    this.refresh();
  }

  handleInput(event) {
    const field = event.target;
    this.updateField(field.id, field);
  }

  refresh() {
    this.summaryMap.forEach((_, fieldId) => {
      const field = this.form.querySelector(`#${fieldId}`);
      this.updateField(fieldId, field);
    });
  }

  updateField(fieldId, field) {
    const target = this.summaryMap.get(fieldId);
    if (!target) {
      return;
    }

    const value = getFieldValue(field);
    target.textContent = value;
    target.dataset.summaryEmpty = value ? 'false' : 'true';

    if (fieldId === 'register_channel') {
      this.updateChannelGroups(field);
    }
  }

  updateChannelGroups(field) {
    const channel = field?.value ?? '';

    this.channelGroups.forEach((group) => {
      const allowed = (group.dataset.summaryChannel || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean);

      const isVisible = channel && (allowed.length === 0 || allowed.includes(channel));
      group.dataset.summaryVisible = isVisible ? 'true' : 'false';
      group.hidden = !isVisible;
    });
  }
}
