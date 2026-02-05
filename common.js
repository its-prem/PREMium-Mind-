// common.js - Enhanced Navbar & Sidebar Loader with Full Functionality

document.addEventListener('DOMContentLoaded', function() {
  
  // Step 1: Load Navbar
  fetch('navbar.html')
    .then(response => {
      if (!response.ok) throw new Error('Navbar load failed');
      return response.text();
    })
    .then(data => {
      document.body.insertAdjacentHTML('afterbegin', data);
      initNavbar();
    })
    .catch(err => console.error('Navbar load error:', err));

  // Step 2: Load Sidebar
  fetch('sidebar.html')
    .then(response => {
      if (!response.ok) throw new Error('Sidebar load failed');
      return response.text();
    })
    .then(data => {
      document.body.insertAdjacentHTML('afterbegin', data);
      initSidebar();
    })
    .catch(err => console.error('Sidebar load error:', err));
});

// ==================== NAVBAR FUNCTIONS ====================
function initNavbar() {
  const navbar = document.querySelector('.navbar');
  
  // Scroll Effect - Navbar shrink on scroll
  window.addEventListener('scroll', function() {
    if (navbar) {
      if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    }
  });

  // Active Link Highlight (current page)
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a').forEach(link => {
    const linkHref = link.getAttribute('href').split('#')[0]; // Remove hash
    if (linkHref === currentPage) {
      link.classList.add('active');
    }
  });

  // Menu Button Click Handler
  const menuBtn = document.getElementById("menu-btn");
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("overlay");

  if (menuBtn && sidebar && overlay) {
    menuBtn.addEventListener("click", function(e) {
      e.stopPropagation();
      openSidebar();
    });
  }

  // Handle smooth scroll for hash links
  document.querySelectorAll('a[href*="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const href = this.getAttribute('href');
      
      // Check if it's a same-page hash link
      if (href.includes('#') && !href.startsWith('http')) {
        const targetPage = href.split('#')[0];
        const hash = href.split('#')[1];
        
        // If same page or empty page (just #hash)
        if (targetPage === '' || targetPage === window.location.pathname.split('/').pop()) {
          e.preventDefault();
          const targetElement = document.getElementById(hash);
          if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
          }
        }
      }
    });
  });
}

// ==================== SIDEBAR FUNCTIONS ====================
function initSidebar() {
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("overlay");
  const closeBtn = document.getElementById("close-sidebar-btn");
  const profilePhoto = document.getElementById("profilePhoto");
  const changePhotoBtn = document.getElementById("change-photo-btn");
  const photoInput = document.getElementById("photo-input");
  const loginBtnSidebar = document.getElementById("login-btn-sidebar");
  const logoutBtn = document.getElementById("logout-btn");

  // Close Sidebar Function
  window.closeSidebar = function() {
    if (sidebar && overlay) {
      sidebar.classList.remove("active");
      overlay.classList.remove("active");
      document.body.style.overflow = ''; // Enable scroll
    }
  };

  // Open Sidebar Function
  window.openSidebar = function() {
    if (sidebar && overlay) {
      sidebar.classList.add("active");
      overlay.classList.add("active");
      document.body.style.overflow = 'hidden'; // Disable scroll
    }
  };

  // Close Button Click
  if (closeBtn) {
    closeBtn.addEventListener("click", function(e) {
      e.stopPropagation();
      closeSidebar();
    });
  }

  // Overlay Click - Close Sidebar
  if (overlay) {
    overlay.addEventListener("click", function() {
      closeSidebar();
    });
  }

  // Sidebar Links Click - Close on navigation
  document.querySelectorAll(".sidebar ul li a").forEach(link => {
    link.addEventListener("click", function(e) {
      const href = this.getAttribute('href');
      
      // Don't close for hash links on same page
      if (!href.startsWith('#') && !href.includes('#')) {
        closeSidebar();
      } else if (href.includes('#')) {
        // For cross-page hash links, still close
        const targetPage = href.split('#')[0];
        if (targetPage && targetPage !== window.location.pathname.split('/').pop()) {
          closeSidebar();
        }
      }
    });
  });

  // Active Link in Sidebar
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.sidebar ul li a').forEach(link => {
    const linkHref = link.getAttribute('href').split('#')[0];
    if (linkHref === currentPage) {
      link.classList.add('active-sidebar');
    }
  });

  // Profile Photo Click - Open file input
  if (profilePhoto && photoInput) {
    profilePhoto.addEventListener('click', function() {
      photoInput.click();
    });
  }

  // Change Photo Button Click
  if (changePhotoBtn && photoInput) {
    changePhotoBtn.addEventListener('click', function(e) {
      e.stopPropagation();
      photoInput.click();
    });
  }

  // Login Button Click
  if (loginBtnSidebar) {
    loginBtnSidebar.addEventListener('click', function() {
      window.location.href = 'login.html';
    });
  }

  // Logout Button Click (will be overridden by Firebase if present)
  if (logoutBtn) {
    logoutBtn.addEventListener('click', function() {
      if (typeof handleLogout === 'function') {
        handleLogout();
      } else {
        alert('Logout functionality not available');
      }
    });
  }

  // Handle Window Resize - Close sidebar on desktop
  window.addEventListener('resize', function() {
    if (window.innerWidth > 768) {
      closeSidebar();
    }
  });

  // Prevent sidebar from closing when clicking inside it
  if (sidebar) {
    sidebar.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  }

  // ESC key to close sidebar
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeSidebar();
    }
  });
}

// ==================== UTILITY FUNCTIONS ====================

// Smooth scroll to element by ID
function scrollToElement(elementId) {
  const element = document.getElementById(elementId);
  if (element) {
    element.scrollIntoView({ 
      behavior: 'smooth', 
      block: 'start' 
    });
  }
}

// Check if element is in viewport
function isInViewport(element) {
  const rect = element.getBoundingClientRect();
  return (
    rect.top >= 0 &&
    rect.left >= 0 &&
    rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
    rect.right <= (window.innerWidth || document.documentElement.clientWidth)
  );
}

// Page load animation (optional)
window.addEventListener('load', function() {
  const navbar = document.querySelector('.navbar');
  if (navbar) {
    navbar.style.opacity = '0';
    setTimeout(() => {
      navbar.style.transition = 'opacity 0.5s ease';
      navbar.style.opacity = '1';
    }, 100);
  }
});

// Console welcome message (optional)
console.log('%c🎓 PREMium Mind', 'font-size: 20px; font-weight: bold; color: #000;');
console.log('%cWebsite loaded successfully!', 'font-size: 12px; color: #666;');