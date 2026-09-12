  <!-- Quick View Modal Backbone -->
  <div class="meesho-modal-backdrop" id="quick-view-modal">
    <div class="meesho-modal-box">
      <button class="modal-close-btn" aria-label="Close modal">&times;</button>
      <div class="modal-dynamic-content">
        <!-- Dynamically loaded via api/search.php?action=quick_view -->
      </div>
    </div>
  </div>

  <!-- Meesho Global Footer -->
  <footer class="meesho-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-col">
          <div class="brand-logo" style="margin-bottom:12px;">
            youshoo
            <span class="brand-tag">Market</span>
          </div>
          <p style="font-size:13px; color:#666; line-height:1.6; margin-bottom:16px;">
            Youshoo is India's premier online shopping destination offering over 50 lakh+ high quality products at the lowest wholesale prices. Discover fashion, sarees, kurtis, beauty, electronics & daily essentials with 100% Free Delivery.
          </p>
          <div style="display:flex; gap:12px;">
            <a href="#" style="width:36px; height:36px; border-radius:50%; background:#f1f1f1; display:flex; align-items:center; justify-content:center; color:#9f2089;"><i class="fab fa-facebook-f"></i></a>
            <a href="#" style="width:36px; height:36px; border-radius:50%; background:#f1f1f1; display:flex; align-items:center; justify-content:center; color:#9f2089;"><i class="fab fa-instagram"></i></a>
            <a href="#" style="width:36px; height:36px; border-radius:50%; background:#f1f1f1; display:flex; align-items:center; justify-content:center; color:#9f2089;"><i class="fab fa-youtube"></i></a>
            <a href="#" style="width:36px; height:36px; border-radius:50%; background:#f1f1f1; display:flex; align-items:center; justify-content:center; color:#9f2089;"><i class="fab fa-linkedin-in"></i></a>
          </div>
        </div>

        <div class="footer-col">
          <h4>Shop By Categories</h4>
          <ul>
            <li><a href="/MEESHO/index.php?category=women-ethnic">Women Ethnic</a></li>
            <li><a href="/MEESHO/index.php?category=women-western">Women Western</a></li>
            <li><a href="/MEESHO/index.php?category=men">Men's Fashion</a></li>
            <li><a href="/MEESHO/index.php?category=kids">Kids & Toys</a></li>
            <li><a href="/MEESHO/index.php?category=home-kitchen">Home & Kitchen</a></li>
            <li><a href="/MEESHO/index.php?category=beauty-health">Beauty & Cosmetics</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Become a Supplier</h4>
          <ul>
            <li><a href="/MEESHO/supplier/index.php" style="color:#9f2089; font-weight:700;">Start Selling at 0% Commission</a></li>
            <li><a href="/MEESHO/supplier/index.php">Supplier Registration</a></li>
            <li><a href="/MEESHO/supplier/index.php">Supplier Login</a></li>
            <li><a href="/MEESHO/admin/login.php">Admin Management Suite</a></li>
            <li><a href="#">Shipping & Returns Policy</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Reach out to us</h4>
          <p style="font-size:13px; color:#666; margin-bottom:10px;">
            <i class="fas fa-envelope" style="color:#9f2089; margin-right:6px;"></i> help@youshoo.com
          </p>
          <p style="font-size:13px; color:#666; margin-bottom:14px;">
            <i class="fas fa-phone-alt" style="color:#9f2089; margin-right:6px;"></i> 1800-120-1234 (Toll Free)
          </p>
          <div style="background:#fdfafc; border:1px solid #f9d5ef; padding:12px; border-radius:8px;">
            <div style="font-size:12px; font-weight:700; color:#56034c; margin-bottom:4px;">Demo Accounts:</div>
            <div style="font-size:11.5px; color:#555;"><strong>Admin:</strong> admin@youshoo.com / admin123</div>
            <div style="font-size:11.5px; color:#555;"><strong>Supplier:</strong> supplier@youshoo.com / seller123</div>
            <div style="font-size:11.5px; color:#555;"><strong>Customer:</strong> customer@youshoo.com / user123</div>
          </div>
        </div>
      </div>

      <div class="footer-bottom">
        <div>&copy; <?php echo date('Y'); ?> Youshoo Inc. All rights reserved. Built with PHP, MySQL & modern Web animations.</div>
        <div style="display:flex; gap:16px;">
          <span>100% Safe & Secure Payments</span>
          <span>Cash On Delivery</span>
          <span>7-Day Return Policy</span>
        </div>
      </div>
    </div>
  </footer>

  <!-- Meesho Interactive Script -->
  <script src="/MEESHO/assets/js/meesho.js"></script>
</body>
</html>
