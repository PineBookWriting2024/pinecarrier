(function () {
  function initQuoteWizard(wrap) {
    if (!wrap || wrap.dataset.qwInitialized === "1") return;
    wrap.dataset.qwInitialized = "1";

    const form = wrap.querySelector("form");
    const thanks = wrap.querySelector(".qw-thanks");
    const steps = Array.from(wrap.querySelectorAll(".qw-step"));
    const dots = Array.from(wrap.querySelectorAll(".qw-step-dot"));

    if (!form || !steps.length || !dots.length) return;

    const yearSelect = form.querySelector('select[name="vehicle_year"]');
    if (yearSelect && yearSelect.options.length <= 1) {
      const currentYear = new Date().getFullYear();
      for (let y = currentYear + 1; y >= 1980; y--) {
        const opt = document.createElement("option");
        opt.value = String(y);
        opt.textContent = String(y);
        yearSelect.appendChild(opt);
      }
    }

    let current = 1;

    function setStep(step) {
      current = step;
      steps.forEach(s => s.classList.toggle("is-active", Number(s.dataset.step) === step));

      dots.forEach(d => {
        const n = Number(d.dataset.stepDot);
        d.classList.toggle("is-active", n === step);
        d.classList.toggle("is-done", n < step);
        d.disabled = n > step;
      });

      const openModal = wrap.closest(".modal.show");
      if (openModal) {
        const modalBody = openModal.querySelector(".quote-modal-form-panel") || openModal.querySelector(".modal-body");
        if (modalBody) modalBody.scrollTo({ top: 0, behavior: "smooth" });
      } else {
        window.scrollTo({ top: form.getBoundingClientRect().top + window.scrollY - 120, behavior: "smooth" });
      }
    }

    function validateStep(step) {
      const stepEl = steps.find(s => Number(s.dataset.step) === step);
      if (!stepEl) return true;

      const required = Array.from(stepEl.querySelectorAll("[required]"));
      let ok = true;

      required.forEach(el => el.classList.remove("is-invalid"));

      const radios = required.filter(el => el.type === "radio");
      const radioNames = [...new Set(radios.map(r => r.name))];
      radioNames.forEach(name => {
        const group = stepEl.querySelectorAll(`input[type="radio"][name="${name}"]`);
        const checked = Array.from(group).some(r => r.checked);
        if (!checked) ok = false;
      });

      const checks = required.filter(el => el.type === "checkbox");
      checks.forEach(ch => { if (!ch.checked) ok = false; });

      required
        .filter(el => el.type !== "radio" && el.type !== "checkbox")
        .forEach(el => {
          if (el.tagName === "SELECT") {
            if (!el.value) ok = false;
          } else if (!el.value || !el.value.trim()) {
            ok = false;
          }
        });

      if (!ok) {
        required
          .filter(el => el.type !== "radio" && el.type !== "checkbox")
          .forEach(el => {
            if (el.tagName === "SELECT" && !el.value) el.classList.add("is-invalid");
            if (el.tagName !== "SELECT" && (!el.value || !el.value.trim())) el.classList.add("is-invalid");
          });
      }

      return ok;
    }

    wrap.addEventListener("click", function (e) {
      const nextBtn = e.target.closest("[data-next]");
      const prevBtn = e.target.closest("[data-prev]");

      if (nextBtn) {
        if (validateStep(current)) setStep(Math.min(3, current + 1));
      }

      if (prevBtn) {
        setStep(Math.max(1, current - 1));
      }
    });

    function setStatus(message, isError) {
      let status = wrap.querySelector(".qw-form-status");
      if (!status) {
        status = document.createElement("p");
        status.className = "qw-form-status";
        status.style.margin = "10px 0 0";
        status.style.fontSize = "14px";
        form.appendChild(status);
      }
      status.textContent = message || "";
      status.style.color = isError ? "#d11a2a" : "#2d7a2d";
    }

    form.addEventListener("submit", async function (e) {
      e.preventDefault();
      if (!validateStep(3)) return;

      const submitBtn = form.querySelector('button[type="submit"]');
      const oldBtnText = submitBtn ? submitBtn.textContent : "";

      try {
        if (submitBtn) {
          submitBtn.disabled = true;
          submitBtn.textContent = "Submitting...";
        }

        setStatus("", false);

        const payload = new FormData(form);
        payload.append("page_url", window.location.href);

        const response = await fetch("assets/php/quote-wizard.php", {
          method: "POST",
          body: payload,
          headers: {
            "X-Requested-With": "XMLHttpRequest",
          },
        });

        let result = null;
        let rawText = "";
        try {
          rawText = await response.text();
          result = rawText ? JSON.parse(rawText) : null;
        } catch (err) {
          result = null;
        }

        if (!response.ok || !result || !result.ok) {
          let message = result && result.message ? result.message : "";
          if (!message && rawText) message = rawText.slice(0, 220);
          if (!message) message = "Unable to submit right now. Please try again.";
          setStatus(message, true);
          return;
        }

        form.hidden = true;
        if (thanks) thanks.hidden = false;
      } catch (err) {
        setStatus("Network error. Please try again.", true);
      } finally {
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.textContent = oldBtnText || "Submit Quote";
        }
      }
    });

    setStep(1);
  }

  document.querySelectorAll(".quote-wizard-wrap").forEach(initQuoteWizard);

  if (window.MutationObserver && document.body) {
    const observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        mutation.addedNodes.forEach(function (node) {
          if (!(node instanceof Element)) return;
          if (node.matches && node.matches(".quote-wizard-wrap")) initQuoteWizard(node);
          node.querySelectorAll && node.querySelectorAll(".quote-wizard-wrap").forEach(initQuoteWizard);
        });
      });
    });

    observer.observe(document.body, { childList: true, subtree: true });
  }
})();

