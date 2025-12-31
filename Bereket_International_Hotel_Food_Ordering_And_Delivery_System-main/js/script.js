// Cart Management
let cart = JSON.parse(localStorage.getItem("cart")) || [];

function cleanCart(saveAfterClean = false) {
  if (!Array.isArray(cart)) {
    cart = [];
    if (saveAfterClean) {
      localStorage.setItem("cart", JSON.stringify(cart));
    }
    return;
  }

  const originalLength = cart.length;
  cart = cart.filter((item) => {
    if (!item || typeof item !== "object") {
      return false;
    }

    const hasId = item.id !== undefined && item.id !== null;
    const hasName =
      item.name !== undefined && item.name !== null && item.name !== "";
    const hasPrice =
      item.price !== undefined &&
      item.price !== null &&
      !isNaN(parseFloat(item.price));
    const hasQuantity =
      item.quantity !== undefined &&
      item.quantity !== null &&
      !isNaN(parseInt(item.quantity)) &&
      parseInt(item.quantity) > 0;

    return hasId && hasName && hasPrice && hasQuantity;
  });

  cart = cart.map((item) => ({
    id: item.id,
    name: item.name,
    price: parseFloat(item.price) || 0,
    quantity: parseInt(item.quantity) || 1,
    image: item.image || "🍽️",
  }));

  if (saveAfterClean && cart.length !== originalLength) {
    localStorage.setItem("cart", JSON.stringify(cart));
  }
}
cleanCart(true);

// Built-in fallback menu (used only if server fetch fails)
const defaultMenuItems = [
  {
    id: 1,
    name: " Doro Wat",
    description: "Spicy chicken stew with injera",
    price: 285,
    category: "main-course",
    image: "asset/image/doro.jpg",
  },
  {
    id: 2,
    name: "Tibs",
    description: "Sautéed beef with vegetables",
    price: 320,
    category: "main-course",
    image: "asset/image/tibs.jpg",
  },
  {
    id: 3,
    name: "Kitfo",
    description: "Minced raw beef with spices",
    price: 340,
    category: "main-course",
    image: "asset/image/kitfo.jpg",
  },
  {
    id: 4,
    name: "Shiro",
    description: "Chickpea stew with injera",
    price: 205,
    category: "main-course",
    image: "asset/image/shero.jpg",
  },
  {
    id: 5,
    name: "Firfir",
    description: "Shredded injera with sauce",
    price: 170,
    category: "main-course",
    image: "asset/image/firfir.jpg",
  },
  {
    id: 6,
    name: "Vegetable Samosa",
    description: "Crispy pastry with vegetables",
    price: 58,
    category: "appetizers",
    image: "asset/image/samosa.jpg",
  },
  {
    id: 7,
    name: "Ethiopian Salad",
    description: "Fresh mixed vegetables",
    price: 138,
    category: "appetizers",
    image: "asset/image/salad.jpg",
  },
  {
    id: 8,
    name: "Baklava",
    description: "Sweet pastry with honey",
    price: 115,
    category: "desserts",
    image: "asset/image/baklava.jpg",
  },
  {
    id: 9,
    name: "Tiramisu",
    description: "Italian coffee dessert",
    price: 170,
    category: "desserts",
    image: "asset/image/tiramisu.jpg",
  },
  {
    id: 11,
    name: "Fresh Juice",
    description: "Seasonal fruit juice",
    price: 70,
    category: "beverages",
    image: "asset/image/fresh.jpg",
  },
];

let menuItems = defaultMenuItems.slice();

async function fetchMenuFromServer() {
  try {
    const res = await fetch('php/get_menu.php');
    if (!res.ok) throw new Error('Network response was not ok');
    const data = await res.json();
    if (Array.isArray(data)) {
      menuItems = data.map((item) => {
        // Normalize image path - ensure it's a valid path relative to project root
        let imagePath = item.image;
        
        // Only use default if image is truly empty or null
        if (!imagePath || imagePath.trim() === '' || imagePath === 'null' || imagePath === 'undefined') {
          // Use category-specific defaults to avoid all items showing the same image
          const categoryDefaults = {
            'appetizers': 'asset/image/samosa.jpg',
            'main-course': 'asset/image/doro.jpg',
            'desserts': 'asset/image/baklava.jpg',
            'beverages': 'asset/image/fresh.jpg'
          };
          imagePath = categoryDefaults[item.category] || 'asset/image/doro.jpg';
        } else {
          imagePath = imagePath.trim();
          // Remove any leading ../ or ./ if present (customer pages are at root)
          imagePath = imagePath.replace(/^\.\.?\//, '');
          // Remove leading slashes that might cause issues on WAMP
          imagePath = imagePath.replace(/^\/+/, '');
          
          // If it's a full URL or data URI, use as is
          if (imagePath.startsWith('http://') || imagePath.startsWith('https://') || imagePath.startsWith('data:')) {
            // Keep as is
          } else {
            // Ensure paths starting with asset/ or uploads/ are kept as relative paths
            // This ensures compatibility with WAMP server
            if (!imagePath.startsWith('asset/') && !imagePath.startsWith('uploads/')) {
              // If it's just a filename, assume it's in asset/image/
              if (imagePath.includes('.') && !imagePath.includes('/')) {
                imagePath = 'asset/image/' + imagePath;
              }
            }
          }
        }
        
        return {
          id: parseInt(item.id, 10),
          name: item.name,
          description: item.description,
          price: parseFloat(item.price) || 0,
          category: item.category || 'uncategorized',
          image: imagePath,
        };
      });
    }
  } catch (err) {
    console.warn('Fetching menu failed, using fallback menu:', err);
    // keep using defaultMenuItems which are already in `menuItems`
  }
}

document.addEventListener("DOMContentLoaded", async function () {
  await fetchMenuFromServer();
  cleanCart();
  updateCartCount();
  const hamburger = document.querySelector(".hamburger");
  const navMenu = document.querySelector(".nav-menu");

  if (hamburger) {
    hamburger.addEventListener("click", () => {
      navMenu.classList.toggle("active");
    });
  }

  // Load content based on page
  const currentPage = window.location.pathname.split("/").pop();

  if (currentPage === "menu.html" || currentPage === "menu") {
    loadMenuItems();
    setupSearchAndFilter();
  } else if (currentPage === "cart.html" || currentPage === "cart") {
    loadCartItems();
  } else if (currentPage === "checkout.html" || currentPage === "checkout") {
    loadCheckoutItems();
    setupCheckoutForm();
  } else if (
    currentPage === "index.html" ||
    currentPage === "" ||
    currentPage === "index"
  ) {
    loadPopularItems();
  }
});

// Update cart count in navbar
function updateCartCount() {
  // Ensure we have a valid cart array
  const validCart = Array.isArray(cart) ? cart : [];
  const cartCount = validCart.reduce((sum, item) => {
    const quantity = parseInt(item.quantity) || 0;
    return sum + quantity;
  }, 0);
  const cartCountElements = document.querySelectorAll("#cart-count");
  cartCountElements.forEach((el) => {
    el.textContent = cartCount || 0;
  });
}

function saveCart() {
  cleanCart(false);
  localStorage.setItem("cart", JSON.stringify(cart));
  updateCartCount();
}

function addToCart(itemId) {
  const item = menuItems.find((i) => i.id === itemId);
  if (!item) return;

  const existingItem = cart.find((i) => i.id === itemId);

  if (existingItem) {
    existingItem.quantity += 1;
  } else {
    cart.push({
      id: item.id,
      name: item.name,
      price: item.price,
      quantity: 1,
      image: item.image,
    });
  }

  saveCart();
  showNotification(`${item.name} added to cart!`);
}

// Remove item from cart
function removeFromCart(itemId) {
  cart = cart.filter((item) => item.id !== itemId);
  saveCart();
  loadCartItems();
}

// Update item quantity in cart
function updateQuantity(itemId, newQuantity) {
  if (newQuantity <= 0) {
    removeFromCart(itemId);
    return;
  }

  const item = cart.find((i) => i.id === itemId);
  if (item) {
    item.quantity = parseInt(newQuantity);
    saveCart();
    loadCartItems();
  }
}


function loadMenuItems() {
  const menuGrid = document.getElementById("menu-grid");
  if (!menuGrid) return;

  menuGrid.innerHTML = menuItems
    .map(
      (item) => {
        // Use the image path directly from item (already normalized in fetchMenuFromServer)
        const imageSrc = item.image || 'asset/image/doro.jpg';
        
        return `
    <div class="menu-item" data-category="${item.category}">
      <div class="menu-item-image">
        <img src="${imageSrc}" alt="${item.name}" onerror="this.onerror=null; this.src='asset/image/doro.jpg';" loading="lazy">
      </div>
      <div class="menu-item-content">
        <h3>${item.name}</h3>
        <p>${item.description}</p>
        <div class="menu-item-footer">
          <span class="menu-item-price">ETB ${item.price.toFixed(2)}</span>
          <button class="add-to-cart-btn" onclick="addToCart(${
            item.id
          })">Add to Cart</button>
        </div>
      </div>
    </div>
  `;
      }
    )
    .join("");
}

// Setup search and filter
function setupSearchAndFilter() {
  const searchInput = document.getElementById("search-input");
  const categoryBtns = document.querySelectorAll(".category-btn");
  const menuItems = document.querySelectorAll(".menu-item");

  // Search functionality
  if (searchInput) {
    searchInput.addEventListener("input", (e) => {
      const searchTerm = e.target.value.toLowerCase();
      filterMenuItems(searchTerm, null);
    });
  }

  // Category filter
  categoryBtns.forEach((btn) => {
    btn.addEventListener("click", () => {
      categoryBtns.forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      const category = btn.dataset.category;
      const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";
      filterMenuItems(searchTerm, category === "all" ? null : category);
    });
  });
}

// Filter menu items
function filterMenuItems(searchTerm, category) {
  const menuItems = document.querySelectorAll(".menu-item");

  menuItems.forEach((item) => {
    const itemName = item.querySelector("h3").textContent.toLowerCase();
    const itemCategory = item.dataset.category;

    const matchesSearch = !searchTerm || itemName.includes(searchTerm);
    const matchesCategory = !category || itemCategory === category;

    // Show matching items and hide non‑matching
    item.style.display = matchesSearch && matchesCategory ? "" : "none";
  });
}

// Load cart items
function loadCartItems() {
  const cartItems = document.getElementById("cart-items");
  const emptyCart = document.getElementById("empty-cart");
  const cartSummary = document.getElementById("cart-summary");
  const checkoutBtn = document.getElementById("checkout-btn");

  if (!cartItems) return;

  if (cart.length === 0) {
    if (emptyCart) emptyCart.style.display = "block";
    if (checkoutBtn) checkoutBtn.disabled = true;
    // Update summary to show 0.00 when cart is empty
    updateCartSummary();
    return;
  }

  if (emptyCart) emptyCart.style.display = "none";
  if (checkoutBtn) checkoutBtn.disabled = false;

  cartItems.innerHTML = cart
    .map(
      (item) => `
    <div class="cart-item">
      <div class="cart-item-info">
        <h3>${item.name}</h3>
        <p>ETB ${item.price.toFixed(2)} each</p>
      </div>
      <div class="cart-item-controls">
        <div class="quantity-control">
          <button class="quantity-btn" onclick="updateQuantity(${item.id}, ${
        item.quantity - 1
      })">-</button>
          <input type="number" class="quantity-input" value="${
            item.quantity
          }" min="1" 
                 onchange="updateQuantity(${item.id}, this.value)">
          <button class="quantity-btn" onclick="updateQuantity(${item.id}, ${
        item.quantity + 1
      })">+</button>
        </div>
        <button class="remove-item-btn" onclick="removeFromCart(${
          item.id
        })">Remove</button>
      </div>
      <div class="cart-item-price">ETB ${(item.price * item.quantity).toFixed(
        2
      )}</div>
    </div>
  `
    )
    .join("");

  updateCartSummary();
}

// Update cart summary
function updateCartSummary() {
  // Ensure we have a valid cart array
  const validCart = Array.isArray(cart) ? cart : [];

  // Calculate subtotal with proper fallback
  const subtotal = validCart.reduce((sum, item) => {
    const price = parseFloat(item.price) || 0;
    const quantity = parseInt(item.quantity) || 0;
    return sum + price * quantity;
  }, 0);

  const deliveryFee = 80;
  const total = (subtotal || 0) + deliveryFee;

  const subtotalEl = document.getElementById("subtotal");
  const totalEl = document.getElementById("total");

  if (subtotalEl) subtotalEl.textContent = `ETB ${(subtotal || 0).toFixed(2)}`;
  if (totalEl) totalEl.textContent = `ETB ${(total || 0).toFixed(2)}`;
  const checkoutBtn = document.getElementById("checkout-btn");
  if (checkoutBtn) {
    checkoutBtn.onclick = () => {
      if (validCart.length > 0) {
        window.location.href = "checkout.html";
      }
    };
  }
}

function loadPopularItems() {
  const popularItems = document.getElementById("popular-items");
  if (!popularItems) return;

  const popular = menuItems.slice(0, 6);
  popularItems.innerHTML = popular
    .map(
      (item) => {
        // Use the image path directly from item (already normalized in fetchMenuFromServer)
        const imageSrc = item.image || 'asset/image/doro.jpg';
        
        return `
    <div class="menu-item">
      <div class="menu-item-image">
        <img src="${imageSrc}" alt="${item.name}" onerror="this.onerror=null; this.src='asset/image/doro.jpg';" loading="lazy">
      </div>
      <div class="menu-item-content">
        <h3>${item.name}</h3>
        <p>${item.description}</p>
        <div class="menu-item-footer">
          <span class="menu-item-price">ETB ${item.price.toFixed(2)}</span>
          <button class="add-to-cart-btn" onclick="addToCart(${
            item.id
          })">Add to Cart</button>
        </div>
      </div>
    </div>
  `;
      }
    )
    .join("");
}

function loadCheckoutItems() {
  const checkoutItems = document.getElementById("checkout-items");
  const paymentAmount = document.getElementById("payment-amount");

  if (!checkoutItems) return;
  const validCart = Array.isArray(cart) ? cart : [];

  if (validCart.length === 0) {
    checkoutItems.innerHTML =
      '<p>Your cart is empty. <a href="menu.html">Go to menu</a></p>';
    if (paymentAmount) paymentAmount.value = "ETB 0.00";
    
    const subtotalEl = document.getElementById("checkout-subtotal");
    const deliveryEl = document.getElementById("checkout-delivery");
    const totalEl = document.getElementById("checkout-total");

    if (subtotalEl) subtotalEl.textContent = "ETB 0.00";
    if (deliveryEl) deliveryEl.textContent = "ETB 80.00";
    if (totalEl) totalEl.textContent = "ETB 80.00";
    return;
  }

  checkoutItems.innerHTML = validCart
    .map(
      (item) => `
    <div class="checkout-item">
      <div>
        <strong>${item.name}</strong> x ${item.quantity}
      </div>
      <div>ETB ${(
        (parseFloat(item.price) || 0) * (parseInt(item.quantity) || 0)
      ).toFixed(2)}</div>
    </div>
  `
    )
    .join("");

  const subtotal = validCart.reduce((sum, item) => {
    const price = parseFloat(item.price) || 0;
    const quantity = parseInt(item.quantity) || 0;
    return sum + price * quantity;
  }, 0);
  const deliveryFee = 80;
  const total = (subtotal || 0) + deliveryFee;

  const subtotalEl = document.getElementById("checkout-subtotal");
  const deliveryEl = document.getElementById("checkout-delivery");
  const totalEl = document.getElementById("checkout-total");

  if (subtotalEl) subtotalEl.textContent = `ETB ${(subtotal || 0).toFixed(2)}`;
  if (deliveryEl) deliveryEl.textContent = `ETB ${deliveryFee.toFixed(2)}`;
  if (totalEl) totalEl.textContent = `ETB ${(total || 0).toFixed(2)}`;
  if (paymentAmount) paymentAmount.value = `ETB ${(total || 0).toFixed(2)}`;
}

// Setup checkout form
function setupCheckoutForm() {
  const form = document.getElementById("checkout-form");
  if (!form) return;

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    // JavaScript validation
    const name = document.getElementById("customer-name").value.trim();
    const email = document.getElementById("customer-email").value.trim();
    const phone = document.getElementById("customer-phone").value.trim();
    const address = document.getElementById("delivery-address").value.trim();
    const screenshot = document.getElementById("payment-screenshot").files[0];

    // Validation
    if (!name || !email || !phone || !address || !screenshot) {
      alert("Please fill in all required fields");
      return;
    }

    if (!validateEmail(email)) {
      alert("Please enter a valid email address");
      return;
    }

    if (cart.length === 0) {
      alert("Your cart is empty");
      return;
    }

    // Create FormData
    const formData = new FormData(form);
    formData.append("cart", JSON.stringify(cart));

    // Calculate totals
    const subtotal = cart.reduce(
      (sum, item) => sum + item.price * item.quantity,
      0
    );
    const deliveryFee = 80;
    const total = subtotal + deliveryFee;
    formData.append("subtotal", subtotal);
    formData.append("delivery_fee", deliveryFee);
    formData.append("total", total);

    // Submit to PHP
    try {
      const response = await fetch("php/process_order.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        // Clear cart
        cart = [];
        saveCart();

        // Show success message
        alert(
          "Order placed successfully! You will receive a confirmation email shortly."
        );
        window.location.href = "index.html";
      } else {
        alert("Error: " + (result.message || "Failed to place order"));
      }
    } catch (error) {
      console.error("Error:", error);
      alert("An error occurred. Please try again.");
    }
  });
}

// Email validation
function validateEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

// Show notification
function showNotification(message) {
  // Simple notification (can be enhanced with a toast library)
  const notification = document.createElement("div");
  notification.style.cssText = `
    position: fixed;
    top: 100px;
    right: 20px;
    background-color:rgb(56, 192, 67);
    color: white;
    padding: 15px 20px;
    border-radius: 5px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    z-index: 10000;
    animation: slideIn 0.3s ease;
  `;
  notification.textContent = message;
  document.body.appendChild(notification);

  setTimeout(() => {
    notification.style.animation = "slideOut 0.3s ease";
    setTimeout(() => notification.remove(), 300);
  }, 3000);
}

// Add CSS animations for notification
const style = document.createElement("style");
style.textContent = `
  @keyframes slideIn {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  @keyframes slideOut {
    from {
      transform: translateX(0);
      opacity: 1;
    }
    to {
      transform: translateX(100%);
      opacity: 0;
    }
  }
`;
document.head.appendChild(style);
