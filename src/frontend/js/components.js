/**
 * UI Components: Modals, Toasts, and Notifications
 * Handles all frontend modal and notification functionality
 */

class Modal {
  constructor(id) {
    this.id = id;
    this.overlay = document.querySelector(`[data-modal="${id}"]`);
    if (!this.overlay) {
      console.error(`Modal with id "${id}" not found`);
    }
    this.setupEventListeners();
  }

  setupEventListeners() {
    if (!this.overlay) return;
    
    // Close button
    const closeBtn = this.overlay.querySelector('[data-modal-close]');
    if (closeBtn) {
      closeBtn.addEventListener('click', () => this.close());
    }

    // Click outside to close
    this.overlay.addEventListener('click', (e) => {
      if (e.target === this.overlay) {
        this.close();
      }
    });

    // ESC key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.overlay.classList.contains('active')) {
        this.close();
      }
    });
  }

  open() {
    if (!this.overlay) return;
    this.overlay.hidden = false;
    this.overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  close() {
    if (!this.overlay) return;
    this.overlay.classList.remove('active');
    this.overlay.hidden = true;
    document.body.style.overflow = '';
  }

  toggle() {
    if (!this.overlay) return;
    if (this.overlay.classList.contains('active')) {
      this.close();
    } else {
      this.open();
    }
  }

  getContent(selector) {
    if (!this.overlay) return null;
    return this.overlay.querySelector(selector);
  }

  getForm() {
    return this.getContent('form');
  }

  setContent(html) {
    const content = this.getContent('[data-modal-content]');
    if (content) {
      content.innerHTML = html;
    }
  }

  getFormData() {
    const form = this.getForm();
    if (!form) return null;
    return new FormData(form);
  }

  getFormDataObject() {
    const form = this.getForm();
    if (!form) return {};
    const data = new FormData(form);
    const obj = {};
    for (const [key, value] of data) {
      obj[key] = value;
    }
    return obj;
  }
}

class Toast {
  static container = null;

  static init() {
    if (!Toast.container) {
      Toast.container = document.createElement('div');
      Toast.container.className = 'notification-container';
      document.body.appendChild(Toast.container);
    }
  }

  static show(message, type = 'info', title = '', duration = 3000) {
    Toast.init();

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const iconMap = {
      success: '✓',
      error: '✕',
      warning: '!',
      info: 'ℹ'
    };

    const titleText = title || type.charAt(0).toUpperCase() + type.slice(1);

    toast.innerHTML = `
      <div class="toast-icon">${iconMap[type] || 'ℹ'}</div>
      <div class="toast-content">
        <div class="toast-title">${titleText}</div>
        <div class="toast-message">${message}</div>
      </div>
      <button class="toast-close" type="button">×</button>
    `;

    const closeBtn = toast.querySelector('.toast-close');
    const remove = () => {
      toast.classList.add('removing');
      setTimeout(() => toast.remove(), 300);
    };

    closeBtn.addEventListener('click', remove);

    Toast.container.appendChild(toast);

    if (duration > 0) {
      setTimeout(remove, duration);
    }

    return toast;
  }

  static success(message, title = 'Success', duration = 3000) {
    return Toast.show(message, 'success', title, duration);
  }

  static error(message, title = 'Error', duration = 4000) {
    return Toast.show(message, 'error', title, duration);
  }

  static warning(message, title = 'Warning', duration = 3500) {
    return Toast.show(message, 'warning', title, duration);
  }

  static info(message, title = 'Info', duration = 3000) {
    return Toast.show(message, 'info', title, duration);
  }
}

class FormValidator {
  static rules = {
    required: (value) => value.trim() !== '',
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    number: (value) => !isNaN(value) && value.trim() !== '',
    minLength: (min) => (value) => value.length >= min,
    maxLength: (max) => (value) => value.length <= max,
  };

  static validate(formElement, rules) {
    const errors = {};
    let isValid = true;

    for (const [fieldName, fieldRules] of Object.entries(rules)) {
      const field = formElement.elements[fieldName];
      if (!field) continue;

      const value = field.value;
      const fieldErrors = [];

      for (const rule of fieldRules) {
        let passed = false;
        
        if (typeof rule === 'string') {
          passed = FormValidator.rules[rule]?.(value) ?? false;
        } else if (typeof rule === 'function') {
          passed = rule(value);
        }

        if (!passed) {
          isValid = false;
          fieldErrors.push(rule);
        }
      }

      if (fieldErrors.length > 0) {
        errors[fieldName] = fieldErrors;
      }
    }

    return { isValid, errors };
  }

  static showErrors(formElement, errors) {
    // Clear previous errors
    formElement.querySelectorAll('.form-error').forEach(el => {
      el.remove();
    });
    formElement.querySelectorAll('.form-input, .form-select').forEach(el => {
      el.style.borderColor = '';
    });

    // Show new errors
    for (const [fieldName, fieldErrors] of Object.entries(errors)) {
      const field = formElement.elements[fieldName];
      if (!field) continue;

      field.style.borderColor = 'var(--red)';
      
      const errorMsg = document.createElement('span');
      errorMsg.className = 'form-error';
      errorMsg.textContent = this.getErrorMessage(fieldName, fieldErrors[0]);
      field.parentNode.insertBefore(errorMsg, field.nextSibling);
    }
  }

  static getErrorMessage(fieldName, rule) {
    const messages = {
      required: `${fieldName} is required`,
      email: `Please enter a valid email`,
      number: `Please enter a valid number`,
      minLength: `Minimum length required`,
      maxLength: `Maximum length exceeded`,
    };

    return messages[rule] || `Validation failed for ${fieldName}`;
  }
}

// Initialize toasts
Toast.init();

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { Modal, Toast, FormValidator };
}
