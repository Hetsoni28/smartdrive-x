/**
 * ==========================================================================
 * 🚀 SMARTDRIVE X - TITAN LOGIC ENGINE
 * Enterprise Frontend Architecture: Encapsulated, Throttled, and Secure.
 * ==========================================================================
 */

const SmartDriveEngine = (function () {
  "use strict";

  // ==========================================
  // ⚙️ PRIVATE VARIABLES
  // ==========================================
  const IDLE_TIMEOUT_MINUTES = 15;
  const WARNING_MINUTE = 14;
  let idleTime = 0;
  let idleInterval = null;
  let warningToastEl = null;

  // ==========================================
  // 🛠️ UTILITY: THROTTLE (Performance Optimization)
  // Prevents CPU overload on mousemove/scroll events
  // ==========================================
  const throttle = (func, limit) => {
    let inThrottle;
    return function () {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => (inThrottle = false), limit);
      }
    };
  };

  // ==========================================
  // 1. 🛡️ ANIMATION & PRELOADER INITIALIZATION
  // ==========================================
  const initAnimations = () => {
    if (typeof AOS !== "undefined") {
      AOS.init({
        duration: 800,
        easing: "ease-out-cubic",
        once: true,
        offset: 50,
      });
    }
  };

  // ==========================================
  // 🧭 2. SIDEBAR TOGGLE LOGIC
  // ==========================================
  const initSidebar = () => {
    const sidebar = document.getElementById("mainSidebar");
    const layout = document.querySelector(".dashboard-layout");
    const toggleIcon = document.getElementById("toggleIcon");
    const toggleBtn = document.getElementById("sidebarToggleBtn");

    if (toggleBtn && sidebar) {
      toggleBtn.addEventListener("click", (e) => {
        e.preventDefault();
        sidebar.classList.toggle("collapsed");
        if (layout) layout.classList.toggle("sidebar-minimized");

        if (toggleIcon) {
          if (sidebar.classList.contains("collapsed")) {
            toggleIcon.classList.replace("fa-angle-left", "fa-angle-right");
          } else {
            toggleIcon.classList.replace("fa-angle-right", "fa-angle-left");
          }
        }
      });
    }
  };

  // ==========================================
  // 🌙 3. SMART DARK MODE ENGINE (Bootstrap 5 Compatible)
  // ==========================================
  const initThemeEngine = () => {
    const darkModeToggles = document.querySelectorAll(".theme-toggle-btn, .fa-moon, .fa-sun");
    const body = document.documentElement; // Best practice for CSS vars

    // Apply saved theme instantly
    if (localStorage.getItem("theme") === "dark") {
      body.setAttribute("data-bs-theme", "dark");
      document.body.setAttribute("data-bs-theme", "dark");
      updateIcons("dark");
    }

    function updateIcons(theme) {
      darkModeToggles.forEach(toggle => {
        const icon = toggle.tagName.toLowerCase() === 'i' ? toggle : toggle.querySelector("i");
        if(icon) {
          if(theme === 'dark') icon.classList.replace("fa-moon", "fa-sun");
          else icon.classList.replace("fa-sun", "fa-moon");
        }
      });
    }

    darkModeToggles.forEach(toggle => {
      const clickableElement = toggle.closest('a') || toggle.closest('button') || toggle;
      
      clickableElement.addEventListener("click", function (e) {
        e.preventDefault();
        const currentTheme = body.getAttribute("data-bs-theme");

        if (currentTheme === "dark") {
          body.removeAttribute("data-bs-theme");
          document.body.removeAttribute("data-bs-theme");
          localStorage.setItem("theme", "light");
          updateIcons("light");
        } else {
          body.setAttribute("data-bs-theme", "dark");
          document.body.setAttribute("data-bs-theme", "dark");
          localStorage.setItem("theme", "dark");
          updateIcons("dark");
        }
      });
    });
  };

  // ==========================================
  // 💰 4. DYNAMIC PRICE FORMATTER (INR)
  // ==========================================
  const formatCurrencies = () => {
    const priceElements = document.querySelectorAll(".auto-format-inr:not([data-formatted])");
    
    const formatter = new Intl.NumberFormat("en-IN", {
      style: "currency",
      currency: "INR",
      maximumFractionDigits: 0,
    });

    priceElements.forEach((el) => {
      let value = parseFloat(el.innerText.replace(/[^\d.-]/g, ""));
      if (!isNaN(value)) {
        el.innerText = formatter.format(value);
        el.setAttribute("data-formatted", "true"); // Prevent double formatting
      }
    });
  };

  // ==========================================
  // 🛠️ 5. UI COMPONENTS (Tooltips & Validation)
  // ==========================================
  const initUIComponents = () => {
    // Bootstrap Tooltips
    if (typeof bootstrap !== 'undefined') {
      const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }

    // Client-Side Form Validation
    const forms = document.querySelectorAll(".needs-validation");
    Array.prototype.slice.call(forms).forEach(function (form) {
      form.addEventListener("submit", function (event) {
        if (!form.checkValidity()) {
          event.preventDefault();
          event.stopPropagation();
          // Find first invalid element and focus it
          const firstInvalid = form.querySelector(':invalid');
          if(firstInvalid) firstInvalid.focus();
        }
        form.classList.add("was-validated");
      }, false);
    });
  };

  // ==========================================
  // 🚨 6. ENTERPRISE SESSION SECURITY (Non-Blocking)
  // ==========================================
  const initSessionSecurity = () => {
    // 1. Throttle the reset to execute max once every 2 seconds to save CPU
    const throttledReset = throttle(() => {
      idleTime = 0;
      if (warningToastEl) {
        warningToastEl.style.opacity = '0';
        setTimeout(() => {
            if(warningToastEl) warningToastEl.remove();
            warningToastEl = null;
        }, 300);
      }
    }, 2000);

    document.addEventListener("mousemove", throttledReset);
    document.addEventListener("keypress", throttledReset);
    document.addEventListener("click", throttledReset);
    document.addEventListener("scroll", throttledReset);

    // 2. Check timer every 1 minute
    idleInterval = setInterval(() => {
      idleTime++;

      // Warning Phase (Inject Custom DOM Element instead of blocking alert)
      if (idleTime === WARNING_MINUTE) {
        showSecurityWarning();
      }

      // Execution Phase
      if (idleTime >= IDLE_TIMEOUT_MINUTES) {
        clearInterval(idleInterval);
        
        // Dynamically get the base URL to ensure logout pathing is always correct
        const pathArray = window.location.pathname.split('/');
        const isSubDir = pathArray.includes('admin') || pathArray.includes('customer');
        window.location.href = isSubDir ? "../logout.php" : "logout.php";
      }
    }, 60000);
  };

  // Custom UI Warning (Replaces `alert()`)
  const showSecurityWarning = () => {
    if (warningToastEl) return; // Prevent duplicates
    
    warningToastEl = document.createElement('div');
    warningToastEl.style.cssText = `
        position: fixed; bottom: 30px; right: 30px; z-index: 999999;
        background: #fff; border-left: 5px solid #dc3545; border-radius: 12px;
        padding: 20px 25px; box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        display: flex; align-items: center; gap: 15px; font-family: 'Plus Jakarta Sans', sans-serif;
        transform: translateY(100px); opacity: 0; transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    `;
    
    warningToastEl.innerHTML = `
        <div style="background: rgba(220, 53, 69, 0.1); color: #dc3545; width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
            <i class="fas fa-user-shield"></i>
        </div>
        <div>
            <h6 style="margin: 0; font-weight: 800; color: #1a2624;">Session Expiring</h6>
            <p style="margin: 0; font-size: 0.85rem; color: #6c757d; font-weight: 600;">Inactivity detected. Moving mouse to stay connected.</p>
        </div>
    `;

    document.body.appendChild(warningToastEl);
    
    // Animate In
    requestAnimationFrame(() => {
        warningToastEl.style.transform = 'translateY(0)';
        warningToastEl.style.opacity = '1';
    });
  };

  // ==========================================
  // 🚀 PUBLIC API (Initialization)
  // ==========================================
  return {
    boot: function () {
      initAnimations();
      initSidebar();
      initThemeEngine();
      formatCurrencies();
      initUIComponents();
      initSessionSecurity();
      
      console.log("%c🚀 SmartDrive X Enterprise Engine Loaded", "color: #4da89c; font-weight: bold; font-size: 14px;");
    }
  };
})();

// Initialize the application when DOM is fully parsed
document.addEventListener("DOMContentLoaded", SmartDriveEngine.boot);
