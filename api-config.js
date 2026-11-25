/**
 * Surexoglobal Frontend API Client (Local & Live Path Auto-Resolver)
 *
 * Uses relative local paths (`./api/...`) automatically resolved from this script's
 * location — works identically on:
 *   - Localhost (XAMPP / Laragon / PHP built-in server)
 *   - Live Website: https://ttbilling.in/surexoglobal/
 *   - Live Admin:   https://ttbilling.in/surexoglobal/#/admin
 *
 * Optional override before loading this script:
 *   window.SUREXO_API_BASE = "https://your-domain.com/surexoglobal/api";
 */
(function (global) {
  "use strict";

  function resolveRootUrl() {
    try {
      var scriptEl =
        document.currentScript ||
        document.querySelector('script[src*="api-config.js"]');
      if (scriptEl && scriptEl.src) {
        var dir = new URL("./", scriptEl.src).href;
        // Strip /admin or /admin/... if rewritten from clean /admin URL
        dir = dir.replace(/\/admin(\/.*)?$/i, "/");
        return dir.replace(/\/+$/, "");
      }
    } catch (e) {
      // Fallback to relative path
    }
    return ".";
  }

  var ROOT_URL = resolveRootUrl();
  var API_BASE = (
    typeof global.SUREXO_API_BASE === "string" && global.SUREXO_API_BASE.trim()
      ? global.SUREXO_API_BASE.trim()
      : ROOT_URL + "/api"
  ).replace(/\/+$/, "");

  function getSessionKey() {
    try {
      var key = localStorage.getItem("surexo_session_key");
      if (!key) {
        key =
          "sess_" +
          Date.now().toString(36) +
          "_" +
          Math.random().toString(36).slice(2, 10);
        localStorage.setItem("surexo_session_key", key);
      }
      return key;
    } catch (e) {
      return "sess_guest";
    }
  }

  function getAdminToken() {
    try {
      var raw =
        sessionStorage.getItem("surexo_admin_user") ||
        localStorage.getItem("surexo_admin_user");
      if (!raw) return "";
      var parsed = JSON.parse(raw);
      return (parsed && parsed.token) || "";
    } catch (e) {
      return "";
    }
  }

  function getCustomerToken() {
    try {
      var raw = localStorage.getItem("surexo_user");
      if (!raw) return "";
      var parsed = JSON.parse(raw);
      return (parsed && parsed.token) || "";
    } catch (e) {
      return "";
    }
  }

  async function request(endpoint, options) {
    options = options || {};
    var method = (options.method || "GET").toUpperCase();
    var scope = options.scope || (endpoint.indexOf("admin/") === 0 ? "admin" : "website");
    var url = API_BASE + "/" + endpoint.replace(/^\/+/, "");

    var headers = {
      Accept: "application/json",
      "X-Session-Key": getSessionKey(),
    };

    var token = scope === "admin" ? getAdminToken() : getCustomerToken();
    if (token) {
      headers["Authorization"] = "Bearer " + token;
      headers[scope === "admin" ? "X-Admin-Token" : "X-Customer-Token"] = token;
    }

    // Tunnel PUT/PATCH/DELETE over POST + _method override for full cPanel/ModSecurity compatibility
    var actualMethod = method;
    if (method === "PUT" || method === "PATCH" || method === "DELETE") {
      actualMethod = "POST";
      headers["X-HTTP-Method-Override"] = method;
      url += (url.indexOf("?") === -1 ? "?" : "&") + "_method=" + encodeURIComponent(method);
    }

    var fetchOpts = {
      method: actualMethod,
      headers: headers,
      credentials: "same-origin",
      cache: "no-store",
    };

    if (options.formData instanceof FormData) {
      fetchOpts.body = options.formData;
    } else if (options.body !== undefined && method !== "GET" && method !== "HEAD") {
      headers["Content-Type"] = "application/json";
      fetchOpts.body = JSON.stringify(options.body);
    }

    try {
      var resp = await fetch(url, fetchOpts);
      var text = await resp.text();
      var json = null;
      try {
        json = text ? JSON.parse(text) : null;
      } catch (parseErr) {
        return {
          ok: false,
          status: resp.status,
          message: "Invalid server response",
          rawText: text,
        };
      }

      if (!resp.ok || (json && json.success === false)) {
        return {
          ok: false,
          status: resp.status,
          message: (json && json.message) || "Request failed (" + resp.status + ")",
          data: json ? json.data : null,
          raw: json,
        };
      }

      return {
        ok: true,
        status: resp.status,
        message: (json && json.message) || "OK",
        data: json ? json.data : null,
        raw: json,
      };
    } catch (err) {
      return {
        ok: false,
        status: 0,
        networkError: true,
        message: (err && err.message) || "Network error contacting API",
      };
    }
  }

  function qs(params) {
    var parts = [];
    Object.keys(params || {}).forEach(function (k) {
      var v = params[k];
      if (v !== undefined && v !== null && v !== "") {
        parts.push(encodeURIComponent(k) + "=" + encodeURIComponent(String(v)));
      }
    });
    return parts.length ? "?" + parts.join("&") : "";
  }

  var SurexoAPI = {
    rootUrl: ROOT_URL,
    baseUrl: API_BASE,
    assetPath: function (relPath) {
      return ROOT_URL + "/" + String(relPath || "").replace(/^\.?\//, "");
    },
    request: request,

    website: {
      bootstrap: function () {
        return request("website/bootstrap.php", { method: "GET", scope: "website" });
      },
      getProducts: function (params) {
        return request("website/products.php" + qs(params), { method: "GET", scope: "website" });
      },
      getCategories: function () {
        return request("website/categories.php", { method: "GET", scope: "website" });
      },
      submitReview: function (payload) {
        return request("website/reviews.php", { method: "POST", body: payload, scope: "website" });
      },
      getOrders: function (email) {
        return request("website/orders.php" + qs({ email: email }), { method: "GET", scope: "website" });
      },
      placeOrder: function (payload) {
        return request("website/orders.php", { method: "POST", body: payload, scope: "website" });
      },
      getCoupons: function () {
        return request("website/coupons.php", { method: "GET", scope: "website" });
      },
      applyCoupon: function (code, subtotal) {
        return request("website/coupons.php", {
          method: "POST",
          body: { code: code, subtotal: subtotal },
          scope: "website",
        });
      },
      getCustomRequests: function (email) {
        return request("website/custom_requests.php" + qs({ email: email }), {
          method: "GET",
          scope: "website",
        });
      },
      submitCustomRequest: function (payload) {
        return request("website/custom_requests.php", {
          method: "POST",
          body: payload,
          scope: "website",
        });
      },
      uploadFiles: function (files, folder) {
        var fd = new FormData();
        fd.append("folder", folder || "requests");
        for (var i = 0; i < files.length; i++) {
          fd.append("files[]", files[i]);
        }
        return request("website/upload.php", {
          method: "POST",
          formData: fd,
          scope: "website",
        });
      },
      submitContact: function (payload) {
        return request("website/contact.php", {
          method: "POST",
          body: payload,
          scope: "website",
        });
      },
      login: function (email, password) {
        return request("website/auth.php?action=login", {
          method: "POST",
          body: { email: email, password: password },
          scope: "website",
        });
      },
      register: function (payload) {
        return request("website/auth.php?action=register", {
          method: "POST",
          body: payload,
          scope: "website",
        });
      },
      logout: function () {
        return request("website/auth.php?action=logout", {
          method: "POST",
          scope: "website",
        });
      },
      me: function () {
        return request("website/auth.php?action=me", {
          method: "GET",
          scope: "website",
        });
      },
      updateProfile: function (payload) {
        return request("website/auth.php?action=profile", {
          method: "PUT",
          body: payload,
          scope: "website",
        });
      },
      changePassword: function (payload) {
        return request("website/auth.php?action=change_password", {
          method: "POST",
          body: payload,
          scope: "website",
        });
      },
      forgotPassword: function (email) {
        return request("website/auth.php?action=forgot_password", {
          method: "POST",
          body: { email: email },
          scope: "website",
        });
      },
      syncCart: function (items) {
        return request("website/cart.php", {
          method: "PUT",
          body: { items: items || [] },
          scope: "website",
        });
      },
      syncWishlist: function (productIds) {
        return request("website/wishlist.php", {
          method: "PUT",
          body: { productIds: productIds || [] },
          scope: "website",
        });
      },
    },

    admin: {
      login: function (email, password, remember) {
        return request("admin/auth.php?action=login", {
          method: "POST",
          body: { email: email, password: password, remember: remember !== false },
          scope: "admin",
        });
      },
      logout: function () {
        return request("admin/auth.php?action=logout", {
          method: "POST",
          scope: "admin",
        });
      },
      me: function () {
        return request("admin/auth.php?action=me", {
          method: "GET",
          scope: "admin",
        });
      },
      bootstrap: function () {
        return request("admin/bootstrap.php", {
          method: "GET",
          scope: "admin",
        });
      },
      getDashboard: function () {
        return request("admin/dashboard.php", {
          method: "GET",
          scope: "admin",
        });
      },
      addProduct: function (payload) {
        return request("admin/products.php", { method: "POST", body: payload, scope: "admin" });
      },
      updateProduct: function (id, payload) {
        return request("admin/products.php" + qs({ id: id }), {
          method: "PUT",
          body: Object.assign({ id: id }, payload),
          scope: "admin",
        });
      },
      toggleProductStock: function (id) {
        return request("admin/products.php" + qs({ id: id, action: "toggle_stock" }), {
          method: "PATCH",
          body: { id: id },
          scope: "admin",
        });
      },
      deleteProduct: function (id) {
        return request("admin/products.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      addCategory: function (payload) {
        return request("admin/categories.php", { method: "POST", body: payload, scope: "admin" });
      },
      updateCategory: function (id, payload) {
        return request("admin/categories.php" + qs({ id: id }), {
          method: "PUT",
          body: Object.assign({ id: id }, payload),
          scope: "admin",
        });
      },
      deleteCategory: function (id) {
        return request("admin/categories.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      updateOrder: function (id, payload) {
        return request("admin/orders.php" + qs({ id: id }), {
          method: "PUT",
          body: Object.assign({ id: id }, payload),
          scope: "admin",
        });
      },
      deleteOrder: function (id) {
        return request("admin/orders.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      addReview: function (payload) {
        return request("admin/reviews.php", { method: "POST", body: payload, scope: "admin" });
      },
      updateReview: function (id, payload) {
        return request("admin/reviews.php" + qs({ id: id }), {
          method: "PUT",
          body: Object.assign({ id: id }, payload),
          scope: "admin",
        });
      },
      deleteReview: function (id) {
        return request("admin/reviews.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      updateRequest: function (id, payload) {
        return request("admin/requests.php" + qs({ id: id }), {
          method: "PUT",
          body: Object.assign({ id: id }, payload),
          scope: "admin",
        });
      },
      deleteRequest: function (id) {
        return request("admin/requests.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      addCustomer: function (payload) {
        return request("admin/customers.php", { method: "POST", body: payload, scope: "admin" });
      },
      updateCustomer: function (id, payload) {
        return request("admin/customers.php" + qs({ id: id }), {
          method: "PUT",
          body: Object.assign({ id: id }, payload),
          scope: "admin",
        });
      },
      deleteCustomer: function (id) {
        return request("admin/customers.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      addCoupon: function (payload) {
        return request("admin/coupons.php", { method: "POST", body: payload, scope: "admin" });
      },
      updateCoupon: function (id, payload) {
        return request("admin/coupons.php" + qs({ id: id }), {
          method: "PUT",
          body: Object.assign({ id: id }, payload),
          scope: "admin",
        });
      },
      deleteCoupon: function (id) {
        return request("admin/coupons.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      getMessages: function () {
        return request("admin/messages.php", { method: "GET", scope: "admin" });
      },
      updateMessage: function (id, status) {
        return request("admin/messages.php" + qs({ id: id }), {
          method: "PUT",
          body: { id: id, status: status },
          scope: "admin",
        });
      },
      deleteMessage: function (id) {
        return request("admin/messages.php" + qs({ id: id }), {
          method: "DELETE",
          body: { id: id },
          scope: "admin",
        });
      },
      uploadFiles: function (files, folder) {
        var fd = new FormData();
        fd.append("folder", folder || "products");
        for (var i = 0; i < files.length; i++) {
          fd.append("files[]", files[i]);
        }
        return request("admin/upload.php", {
          method: "POST",
          formData: fd,
          scope: "admin",
        });
      },
    },
  };

  global.SurexoAPI = SurexoAPI;
})(window);
