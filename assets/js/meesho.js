/**
 * MEESHO E-COMMERCE PLATFORM - INTERACTIVE JS & ANIMATIONS
 */

document.addEventListener("DOMContentLoaded", () => {
  initStickyHeader();
  initHeroSlider();
  initLivePurchaseTicker();
  initQuickViewModal();
  initPincodeChecker();
  initSearchAutocomplete();
  initThumbnailGallery();
  initSizeSelector();
});

/* 1. STICKY HEADER ELEVATION */
function initStickyHeader() {
  const header = document.querySelector(".meesho-header");
  if (!header) return;

  window.addEventListener("scroll", () => {
    if (window.scrollY > 40) {
      header.classList.add("scrolled");
    } else {
      header.classList.remove("scrolled");
    }
  });
}

/* 2. HERO PROMO BANNER SLIDER */
function initHeroSlider() {
  const slider = document.querySelector(".hero-slider-container");
  if (!slider) return;

  const track = slider.querySelector(".slider-track");
  const slides = slider.querySelectorAll(".hero-slide");
  const dots = slider.querySelectorAll(".slider-dot");
  const prevBtn = slider.querySelector(".slider-arrow.prev");
  const nextBtn = slider.querySelector(".slider-arrow.next");

  let currentIndex = 0;
  let autoSlideTimer = null;
  const totalSlides = slides.length;

  if (totalSlides <= 1) return;

  function goToSlide(index) {
    if (index < 0) index = totalSlides - 1;
    if (index >= totalSlides) index = 0;
    currentIndex = index;

    track.style.transform = `translateX(-${currentIndex * 100}%)`;

    dots.forEach((dot, idx) => {
      dot.classList.toggle("active", idx === currentIndex);
    });
  }

  function startAutoSlide() {
    stopAutoSlide();
    autoSlideTimer = setInterval(() => {
      goToSlide(currentIndex + 1);
    }, 5000);
  }

  function stopAutoSlide() {
    if (autoSlideTimer) clearInterval(autoSlideTimer);
  }

  if (nextBtn) {
    nextBtn.addEventListener("click", () => {
      goToSlide(currentIndex + 1);
      startAutoSlide();
    });
  }

  if (prevBtn) {
    prevBtn.addEventListener("click", () => {
      goToSlide(currentIndex - 1);
      startAutoSlide();
    });
  }

  dots.forEach((dot, idx) => {
    dot.addEventListener("click", () => {
      goToSlide(idx);
      startAutoSlide();
    });
  });

  slider.addEventListener("mouseenter", stopAutoSlide);
  slider.addEventListener("mouseleave", startAutoSlide);

  startAutoSlide();
}

/* 3. LIVE SOCIAL PROOF TICKER */
function initLivePurchaseTicker() {
  const purchases = [
    { name: "Pooja from Surat", item: "Kashvi Banarasi Silk Saree", time: "just now", img: "https://images.unsplash.com/photo-1610030469983-98e550d6193c?w=100&auto=format&fit=crop&q=80" },
    { name: "Sneha from Pune", item: "Trendy Floral Ruffled Dress", time: "1 min ago", img: "https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?w=100&auto=format&fit=crop&q=80" },
    { name: "Rohan from Jaipur", item: "Urbano Men Solid Cotton Shirt", time: "2 mins ago", img: "https://images.unsplash.com/photo-1602810318383-e386cc2a3ccf?w=100&auto=format&fit=crop&q=80" },
    { name: "Kavita from Kolkata", item: "Glace Cotton King Bedsheet", time: "just now", img: "https://images.unsplash.com/photo-1584589167171-541ce45f1eea?w=100&auto=format&fit=crop&q=80" },
    { name: "Meera from Bengaluru", item: "Kundan & Pearl Choker Set", time: "3 mins ago", img: "https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=100&auto=format&fit=crop&q=80" },
    { name: "Deepak from Lucknow", item: "True Wireless Bluetooth Earbuds", time: "just now", img: "https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=100&auto=format&fit=crop&q=80" }
  ];

  let toast = document.createElement("div");
  toast.className = "live-ticker-toast";
  toast.innerHTML = `
    <img src="" alt="" class="ticker-img">
    <div class="ticker-content">
      <h6 class="ticker-title"></h6>
      <p class="ticker-subtitle"></p>
    </div>
  `;
  document.body.appendChild(toast);

  let pIndex = 0;
  function showNextPurchase() {
    const p = purchases[pIndex % purchases.length];
    toast.querySelector(".ticker-img").src = p.img;
    toast.querySelector(".ticker-title").textContent = `${p.name} purchased`;
    toast.querySelector(".ticker-subtitle").textContent = `${p.item} • ${p.time}`;

    toast.classList.add("show");

    setTimeout(() => {
      toast.classList.remove("show");
    }, 4500);

    pIndex++;
  }

  // Initial delay then recurring
  setTimeout(showNextPurchase, 3000);
  setInterval(showNextPurchase, 14000);
}

/* 4. AJAX ADD TO CART */
window.addToCart = function(productId, size = "Free Size", color = "Default", qty = 1, buttonElement = null) {
  let originalHtml = "";
  if (buttonElement) {
    originalHtml = buttonElement.innerHTML;
    buttonElement.disabled = true;
    buttonElement.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Adding...`;
  }

  const formData = new FormData();
  formData.append("action", "add");
  formData.append("product_id", productId);
  formData.append("size", size);
  formData.append("color", color);
  formData.append("quantity", qty);

  fetch("/MEESHO/api/cart.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      // Update badge
      const cartBadges = document.querySelectorAll(".cart-badge");
      cartBadges.forEach(badge => {
        badge.textContent = data.cart_count;
        badge.classList.remove("bounce");
        void badge.offsetWidth; // trigger reflow
        badge.classList.add("bounce");
      });

      if (buttonElement) {
        buttonElement.innerHTML = `<i class="fas fa-check"></i> Added!`;
        buttonElement.style.background = "#038d63";
        buttonElement.style.color = "#fff";
        buttonElement.style.borderColor = "#038d63";
        setTimeout(() => {
          buttonElement.disabled = false;
          buttonElement.innerHTML = originalHtml;
          buttonElement.style.background = "";
          buttonElement.style.color = "";
          buttonElement.style.borderColor = "";
        }, 1800);
      }

      showToastSuccess("Item added to your Youshoo cart!");
    } else {
      alert(data.message || "Failed to add to cart");
      if (buttonElement) {
        buttonElement.disabled = false;
        buttonElement.innerHTML = originalHtml;
      }
    }
  })
  .catch(err => {
    console.error("Cart error:", err);
    if (buttonElement) {
      buttonElement.disabled = false;
      buttonElement.innerHTML = originalHtml;
    }
  });
};

/* 5. SUCCESS TOAST POPUP */
function showToastSuccess(message) {
  let toast = document.getElementById("meesho-alert-toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.id = "meesho-alert-toast";
    toast.style.cssText = `
      position: fixed;
      top: 80px;
      right: 24px;
      background: #038d63;
      color: #fff;
      padding: 12px 20px;
      border-radius: 8px;
      box-shadow: 0 8px 24px rgba(0,0,0,0.18);
      font-size: 14px;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 9999;
      transform: translateX(100px);
      opacity: 0;
      transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    `;
    document.body.appendChild(toast);
  }

  toast.innerHTML = `<i class="fas fa-check-circle" style="font-size:18px;"></i> ${message}`;
  toast.style.transform = "translateX(0)";
  toast.style.opacity = "1";

  setTimeout(() => {
    toast.style.transform = "translateX(100px)";
    toast.style.opacity = "0";
  }, 2500);
}

/* 6. PINCODE CHECKER */
function initPincodeChecker() {
  const btn = document.querySelector(".pincode-check-btn");
  const input = document.querySelector(".pincode-input");
  const statusBox = document.querySelector(".pincode-status-text");

  if (!btn || !input) return;

  btn.addEventListener("click", () => {
    const pin = input.value.trim();
    if (!pin || pin.length !== 6 || isNaN(pin)) {
      if (statusBox) {
        statusBox.innerHTML = `<span style="color:#dc2626; font-size:12px;"><i class="fas fa-times-circle"></i> Please enter a valid 6-digit Pincode</span>`;
      }
      return;
    }

    btn.textContent = "Checking...";
    setTimeout(() => {
      btn.textContent = "Check";
      if (statusBox) {
        statusBox.innerHTML = `
          <div style="color:#038d63; font-size:12.5px; font-weight:600; margin-top:6px;">
            <i class="fas fa-shipping-fast"></i> Free Delivery available! Expected by <strong>Tomorrow, 5 PM</strong>.
          </div>
        `;
      }
    }, 600);
  });
}

/* 7. QUICK VIEW MODAL */
function initQuickViewModal() {
  const modalBackdrop = document.querySelector(".meesho-modal-backdrop");
  if (!modalBackdrop) return;

  const closeBtn = modalBackdrop.querySelector(".modal-close-btn");
  if (closeBtn) {
    closeBtn.addEventListener("click", () => {
      modalBackdrop.classList.remove("open");
    });
  }

  modalBackdrop.addEventListener("click", (e) => {
    if (e.target === modalBackdrop) {
      modalBackdrop.classList.remove("open");
    }
  });

  window.openQuickView = function(productId) {
    fetch(`/MEESHO/api/search.php?action=quick_view&id=${productId}`)
      .then(r => r.json())
      .then(p => {
        if (!p || !p.id) return;
        const box = modalBackdrop.querySelector(".modal-dynamic-content");
        if (!box) return;

        const sizesArr = (p.sizes || "Free Size").split(",");
        const sizeChipsHtml = sizesArr.map((s, i) => `
          <div class="size-chip ${i === 0 ? 'selected' : ''}" onclick="selectModalSize(this, '${s.trim()}')">${s.trim()}</div>
        `).join("");

        box.innerHTML = `
          <div style="display:grid; grid-template-columns: 280px 1fr; gap:24px; padding:24px;">
            <div style="border-radius:8px; overflow:hidden; border:1px solid #e6e9ef;">
              <img src="${p.primary_image || 'https://placehold.co/400x500'}" style="width:100%; height:320px; object-fit:cover;">
            </div>
            <div>
              <span class="delivery-badge" style="margin-bottom:8px;"><i class="fas fa-truck"></i> Free Delivery</span>
              <h3 style="font-size:18px; font-weight:700; color:#333; margin-bottom:8px;">${p.title}</h3>
              <div style="display:flex; align-items:baseline; gap:10px; margin-bottom:12px;">
                <span style="font-size:24px; font-weight:800; color:#333;">₹${Math.round(p.price)}</span>
                <span style="font-size:14px; color:#888; text-decoration:line-through;">₹${Math.round(p.mrp)}</span>
                <span style="font-size:14px; font-weight:700; color:#038d63;">${p.discount_percent}% off</span>
              </div>
              <p style="font-size:13px; color:#666; margin-bottom:14px; line-height:1.5;">${p.description.substring(0, 160)}...</p>
              <div style="margin-bottom:18px;">
                <div style="font-size:12px; font-weight:700; margin-bottom:6px;">Select Size:</div>
                <div class="size-chips-wrap" id="modal-size-container">${sizeChipsHtml}</div>
                <input type="hidden" id="modal-selected-size" value="${sizesArr[0].trim()}">
              </div>
              <div style="display:flex; gap:10px;">
                <button class="btn-buy-now" style="padding:10px 16px; font-size:14px;" onclick="addToCart(${p.id}, document.getElementById('modal-selected-size').value, 'Default', 1, this)">
                  <i class="fas fa-shopping-bag"></i> Add to Cart
                </button>
                <a href="/MEESHO/product.php?id=${p.id}" class="btn-add-cart" style="padding:10px 16px; font-size:14px; text-align:center;">
                  View Details &rarr;
                </a>
              </div>
            </div>
          </div>
        `;
        modalBackdrop.classList.add("open");
      });
  };
}

window.selectModalSize = function(el, size) {
  const container = document.getElementById("modal-size-container");
  if (!container) return;
  container.querySelectorAll(".size-chip").forEach(c => c.classList.remove("selected"));
  el.classList.add("selected");
  document.getElementById("modal-selected-size").value = size;
};

/* 8. THUMBNAIL SWITCHER ON DETAIL PAGE */
function initThumbnailGallery() {
  const mainImg = document.getElementById("detail-main-img");
  const thumbs = document.querySelectorAll(".thumb-item");

  if (!mainImg || !thumbs.length) return;

  thumbs.forEach(thumb => {
    thumb.addEventListener("click", () => {
      thumbs.forEach(t => t.classList.remove("active"));
      thumb.classList.add("active");
      const newSrc = thumb.getAttribute("data-full-img");
      if (newSrc) {
        mainImg.style.opacity = "0.5";
        mainImg.src = newSrc;
        setTimeout(() => {
          mainImg.style.opacity = "1";
        }, 150);
      }
    });
  });
}

/* 9. SIZE SELECTOR ON DETAIL PAGE */
function initSizeSelector() {
  const chips = document.querySelectorAll(".detail-size-chip");
  const hiddenInput = document.getElementById("selected-product-size");

  if (!chips.length) return;

  chips.forEach(chip => {
    chip.addEventListener("click", () => {
      chips.forEach(c => c.classList.remove("selected"));
      chip.classList.add("selected");
      if (hiddenInput) {
        hiddenInput.value = chip.getAttribute("data-size");
      }
    });
  });
}

/* 10. SEARCH AUTOCOMPLETE */
function initSearchAutocomplete() {
  const input = document.getElementById("header-search-input");
  const dropdown = document.getElementById("search-results-dropdown");

  if (!input || !dropdown) return;

  let debounceTimer = null;

  input.addEventListener("input", () => {
    clearTimeout(debounceTimer);
    const q = input.value.trim();

    if (q.length < 2) {
      dropdown.style.display = "none";
      return;
    }

    debounceTimer = setTimeout(() => {
      fetch(`/MEESHO/api/search.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(items => {
          if (!items || !items.length) {
            dropdown.innerHTML = `<div style="padding:14px; font-size:13px; color:#888; text-align:center;">No matching products found</div>`;
            dropdown.style.display = "block";
            return;
          }

          dropdown.innerHTML = items.map(p => `
            <a href="/MEESHO/product.php?id=${p.id}" class="search-result-item">
              <img src="${p.primary_image || 'https://placehold.co/50'}" alt="">
              <div style="flex:1;">
                <div style="font-size:13px; font-weight:600; color:#333; margin-bottom:2px;">${p.title}</div>
                <div style="font-size:12px; color:#038d63; font-weight:700;">₹${Math.round(p.price)} <span style="font-size:11px; color:#888; text-decoration:line-through;">₹${Math.round(p.mrp)}</span></div>
              </div>
            </a>
          `).join("");

          dropdown.style.display = "block";
        })
        .catch(() => {
          dropdown.style.display = "none";
        });
    }, 250);
  });

  document.addEventListener("click", (e) => {
    if (!dropdown.contains(e.target) && e.target !== input) {
      dropdown.style.display = "none";
    }
  });
}
