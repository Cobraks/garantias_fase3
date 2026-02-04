export default class Stepper {
  constructor(container) {
    this.container = container;
    this.steps = Array.from(container.querySelectorAll('[data-step]'));
    this.triggers = Array.from(container.querySelectorAll('[data-step-trigger]'));
    this.currentIndex = 0;
    this.maxVisitedIndex = 0;
    this.changeCallback = null;
    this.connectors = Array.from(container.querySelectorAll('.tabs__connector .connector'));

    this.triggers.forEach((trigger) => {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        const index = Number(trigger.dataset.stepTrigger);
        if (Number.isNaN(index)) {
          return;
        }

        if (index <= this.maxVisitedIndex + 1) {
          this.goTo(index);
        }
      });
    });

    this.update();
  }

  onChange(callback) {
    this.changeCallback = callback;
    this.emit();
  }

  getCurrentStep() {
    return this.steps[this.currentIndex] ?? null;
  }

  isFirst() {
    return this.currentIndex === 0;
  }

  isLast() {
    return this.currentIndex === this.steps.length - 1;
  }

  next() {
    if (!this.isLast()) {
      this.goTo(this.currentIndex + 1);
    }
  }

  prev() {
    if (!this.isFirst()) {
      this.goTo(this.currentIndex - 1);
    }
  }

  goTo(index) {
    if (index < 0 || index >= this.steps.length || index === this.currentIndex) {
      return;
    }

    this.currentIndex = index;
    this.maxVisitedIndex = Math.max(this.maxVisitedIndex, index);
    this.update();
    this.emit();
  }

  update() {
    this.steps.forEach((step, position) => {
      const isActive = position === this.currentIndex;
      step.classList.toggle('is-active', isActive);
      step.toggleAttribute('hidden', !isActive);
      step.setAttribute('aria-hidden', String(!isActive));
      step.dataset.stepCurrent = String(isActive);
    });

    this.triggers.forEach((trigger, position) => {
      const isActive = position === this.currentIndex;
      trigger.classList.toggle('is-active', isActive);
      trigger.classList.toggle('is-completed', position < this.currentIndex);
      trigger.setAttribute('aria-current', isActive ? 'step' : 'false');
    });

    this.connectors.forEach((connector, position) => {
      connector.classList.toggle('is-active', position < this.currentIndex);
    });
  }

  emit() {
    if (typeof this.changeCallback === 'function') {
      this.changeCallback({
        index: this.currentIndex,
        isFirst: this.isFirst(),
        isLast: this.isLast(),
        total: this.steps.length,
      });
    }
  }
}
