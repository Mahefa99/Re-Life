document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('input[type="password"]').forEach((field, index) => {
    const showText = document.body.dataset.passwordShow;
    const hideText = document.body.dataset.passwordHide;
    if (!showText || !hideText) return;

    if (!field.id) {
      let id = `password-field-${index}`;
      while (document.getElementById(id)) id += '-input';
      field.id = id;
    }
    const previousLabel = field.previousElementSibling;
    if (previousLabel?.tagName === 'LABEL' && !previousLabel.htmlFor) {
      previousLabel.htmlFor = field.id;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'password-field';
    field.before(wrapper);
    wrapper.append(field);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'password-toggle';
    button.setAttribute('aria-controls', field.id);
    const label = field.labels?.[0]?.textContent.trim() || '';
    const updateButton = () => {
      const action = field.type === 'password' ? showText : hideText;
      button.textContent = action;
      button.setAttribute('aria-label', label ? `${action} : ${label}` : action);
    };
    button.addEventListener('click', () => {
      field.type = field.type === 'password' ? 'text' : 'password';
      updateButton();
    });
    updateButton();
    wrapper.append(button);
  });

  document.querySelectorAll('[data-submit-on-change]').forEach((field) => {
    field.addEventListener('change', () => field.form.requestSubmit());
  });
  const toggle = document.querySelector('.menu-toggle');
  const panel = document.querySelector('.nav-panel');

  if (toggle && panel) {
    const closeMenu = () => {
      toggle.setAttribute('aria-expanded', 'false');
      panel.classList.remove('is-open');
      document.body.classList.remove('menu-open');
    };

    toggle.addEventListener('click', () => {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      panel.classList.toggle('is-open', open);
      document.body.classList.toggle('menu-open', open);
    });

    panel.querySelectorAll('a').forEach((link) => link.addEventListener('click', closeMenu));
    document.addEventListener('click', (event) => {
      if (panel.classList.contains('is-open') && !panel.contains(event.target) && !toggle.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && panel.classList.contains('is-open')) {
        closeMenu();
        toggle.focus();
      }
    });
    window.addEventListener('resize', () => {
      if (window.innerWidth > 820) closeMenu();
    });
  }

  const category = document.querySelector('#categorie-produit');
  const bookFields = document.querySelector('[data-book-fields]');
  const author = document.querySelector('#auteur');
  const sizeLabel = document.querySelector('[data-size-label]');

  if (category && bookFields) {
    const syncProductFields = () => {
      const isBook = category.value === 'Livres';
      bookFields.hidden = !isBook;
      bookFields.querySelectorAll('input').forEach((field) => { field.disabled = !isBook; });
      if (author) author.required = isBook;
      if (sizeLabel) sizeLabel.textContent = isBook ? sizeLabel.dataset.formatText : sizeLabel.dataset.sizeText;
    };
    category.addEventListener('change', syncProductFields);
    syncProductFields();
  }

  const donationTypes = document.querySelectorAll('input[name="types_don[]"]');
  const donationGroup = document.querySelector('[data-donation-group]');
  const donationTypeError = document.querySelector('[data-donation-type-error]');
  const donationDescriptionLabel = document.querySelector('[data-donation-description-label]');
  const donationDescription = document.querySelector('[data-donation-description]');

  if (donationTypes.length && donationDescriptionLabel && donationDescription) {
    const syncDonationType = () => {
      const clothes = document.querySelector('input[name="types_don[]"][value="vetements"]')?.checked;
      const books = document.querySelector('input[name="types_don[]"][value="livres"]')?.checked;
      const hasSelection = clothes || books;
      const mode = clothes && books ? 'mixed' : (books ? 'books' : 'clothes');

      donationDescriptionLabel.textContent = donationDescriptionLabel.dataset[mode];
      donationDescription.placeholder = donationDescription.dataset[`${mode}Placeholder`];
      donationGroup?.classList.toggle('has-error', !hasSelection);
      if (donationTypeError) donationTypeError.hidden = hasSelection;
      donationTypes[0].setCustomValidity(hasSelection ? '' : (donationGroup?.dataset.requiredMessage || 'Select at least one option.'));
    };
    donationTypes.forEach((input) => input.addEventListener('change', syncDonationType));
    donationTypes[0].form?.addEventListener('submit', syncDonationType);
    syncDonationType();
  }
});
