/**
 * @file
 * Manages the reset button on search.
 *
 * TODO: make this jQuery, and make it work!
 */

window.onload = function () {
  let reset_button = document.getElementById('resetForm');

  reset_button.style.display = 'inline-block';
  reset_button.addEventListener('click', function (e) {
    e.preventDefault();

    var checks = document.querySelectorAll('input.cb-f');
    for (var i = 0; i < checks.length; i++) {
      checks[i].checked = false;
    }

    document.getElementById('searchForm').submit();
  });
};
