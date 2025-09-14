<?php

function render_message(){

    if (isset($_SESSION["signup_success"])) {

        $message = $_SESSION["signup_success"];

          // Success notice uses the same layout as the green box in form-content.php
          echo '<div class="success-message">'
              . "<i class='bx bx-check-circle'></i> "
              . htmlspecialchars($message)
              . '</div>';

        unset($_SESSION["signup_success"]);

    }

    if (isset($_SESSION["login_errors"])) {

        $errors = $_SESSION["login_errors"];

          // Error notice matches the success layout but in red
          foreach($errors as $error) {
                echo '<div class="error-message">'
                    . "<i class='bx bx-error-circle'></i> "
                    . htmlspecialchars($error)
                    . '</div>';
          }

        unset($_SESSION["login_errors"]);

    }

}