const loginForm = document.getElementById("loginForm");
const username = document.getElementById("username");
const password = document.getElementById("password");
const togglePassword = document.getElementById("togglePassword");
const message = document.getElementById("message");

if (toggleRegisterPassword && registerPassword) {
  toggleRegisterPassword.addEventListener("click", function () {
    const icon = toggleRegisterPassword.querySelector("i");

    if (registerPassword.type === "password") {
      registerPassword.type = "text";
      icon.classList.remove("fa-eye");
      icon.classList.add("fa-eye-slash");
    } else {
      registerPassword.type = "password";
      icon.classList.remove("fa-eye-slash");
      icon.classList.add("fa-eye");
    }
  });
}

loginForm.addEventListener("submit", function (event) {
  event.preventDefault();

  const enteredUsername = username.value.trim();
  const enteredPassword = password.value.trim();

  message.className = "";

  if (enteredUsername === "" || enteredPassword === "") {
    message.textContent = "Please enter username and password.";
    message.classList.add("error");
    return;
  }

  if (enteredUsername === "admin" && enteredPassword === "1234") {
    message.textContent = "Login successful!";
    message.classList.add("success");
  } else {
    message.textContent = "Invalid username or password.";
    message.classList.add("error");
  }
});
const registerForm = document.getElementById("registerForm");
const registerPassword = document.getElementById("registerPassword");
const confirmPassword = document.getElementById("confirmPassword");
const toggleRegisterPassword = document.getElementById("toggleRegisterPassword");
const registerMessage = document.getElementById("registerMessage");

if (toggleRegisterPassword) {
  toggleRegisterPassword.addEventListener("click", function () {
    if (registerPassword.type === "password") {
      registerPassword.type = "text";
      toggleRegisterPassword.textContent = "Hide";
    } else {
      registerPassword.type = "password";
      toggleRegisterPassword.textContent = "Show";
    }
  });
}

if (registerForm) {
  registerForm.addEventListener("submit", function (event) {
    event.preventDefault();

    const fullName = document.getElementById("fullName").value.trim();
    const email = document.getElementById("email").value.trim();
    const studentId = document.getElementById("studentId").value.trim();
    const passwordValue = registerPassword.value.trim();
    const confirmPasswordValue = confirmPassword.value.trim();

    registerMessage.className = "";

    if (
      fullName === "" ||
      email === "" ||
      studentId === "" ||
      passwordValue === "" ||
      confirmPasswordValue === ""
    ) {
      registerMessage.textContent = "Please fill all fields.";
      registerMessage.classList.add("error");
      return;
    }

    if (passwordValue !== confirmPasswordValue) {
      registerMessage.textContent = "Passwords do not match.";
      registerMessage.classList.add("error");
      return;
    }

   registerMessage.textContent = "Registration successful!";
registerMessage.classList.add("success");

    setTimeout(function () {
    window.location.href = "dashboard.html";
    }, 1000);
  });
}
if (toggleRegisterPassword && registerPassword) {
  toggleRegisterPassword.addEventListener("click", function () {
    if (registerPassword.type === "password") {
      registerPassword.type = "text";
      toggleRegisterPassword.textContent = "🙈";
    } else {
      registerPassword.type = "password";
      toggleRegisterPassword.textContent = "👁";
    }
  });
}
// Later backend/database values can update these fields.
// For now, placeholders are shown in the dashboard cards.

const dashboardData = {
  upcomingOrders: "--",
  totalOrders: "--",
  completedOrders: "--",
  totalSpent: "--"
};

document.getElementById("upcomingOrders").textContent = dashboardData.upcomingOrders;
document.getElementById("totalOrders").textContent = dashboardData.totalOrders;
document.getElementById("completedOrders").textContent = dashboardData.completedOrders;
document.getElementById("totalSpent").textContent = dashboardData.totalSpent;

if (username.value === "admin" && password.value === "1234") {
  message.textContent = "Login successful!";
  message.classList.add("success");

  setTimeout(function () {
    window.location.href = "Dashboard.html";
  }, 1000);
}
document.getElementById("ricePrice").textContent = "Rs.150";
document.getElementById("kottuPrice").textContent = "Rs.300";
const cartItems = document.querySelectorAll(".cart-item");

cartItems.forEach(function (item) {
  const minusBtn = item.querySelector(".minus-btn");
  const plusBtn = item.querySelector(".plus-btn");
  const quantityText = item.querySelector(".quantity");

  plusBtn.addEventListener("click", function () {
    let quantity = Number(quantityText.textContent);
    quantity += 1;
    quantityText.textContent = quantity;
  });

  minusBtn.addEventListener("click", function () {
    let quantity = Number(quantityText.textContent);

    if (quantity > 1) {
      quantity -= 1;
      quantityText.textContent = quantity;
    }
  });
});