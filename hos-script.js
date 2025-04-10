//This script is for hosannaunom website

document.addEventListener("DOMContentLoaded", function () {
    // Find the target form element
    const form = document.getElementById("cpff_custom_form_1");
  
    if (form) {
      // Create the new HTML block
      const wrapper = document.createElement("div");
      wrapper.innerHTML = `
              <div id="cleaning-or-laundry">
                  <label>
                      <input type="checkbox" name="cleaning" value="Cleaning" onclick="checkCheckbox();">
                      Cleaning
                  </label>
                  <label>
                      <input type="checkbox" name="laundry" value="laundry" onclick="checkCheckbox();">
                      Laundry
                  </label>
              </div>
          `;
  
      // Insert before the form
      form.parentNode.insertBefore(wrapper, form);
    }
  });
  
  function checkCheckbox() {
    let checkBoxCleaning = document.querySelector('input[name="cleaning"]');
    if (checkBoxCleaning.checked) {
      document.getElementById("cpff_custom_form_1").style.display = "block";
    } else {
      document.getElementById("cpff_custom_form_1").style.display = "none";
    }
    let checkBoxLaundry = document.querySelector('input[name="laundry"]');
    if (checkBoxLaundry.checked) {
      document.getElementById("cpff_custom_form_2").style.display = "block";
    } else {
      document.getElementById("cpff_custom_form_2").style.display = "none";
    }
  }