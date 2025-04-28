<?php

function render_errors(){

    if (isset($_SESSION["signup_errors"])) {

        $errors = $_SESSION["signup_errors"];

        foreach($errors as $error) {

            echo '<h5 style="color:firebrick">' . $error . '</h5>';

        }

        unset($_SESSION["signup_errors"]);

    }

}