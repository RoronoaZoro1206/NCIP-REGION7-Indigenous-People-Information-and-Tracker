<?php

function render_message(){

    if (isset($_SESSION["signup_success"])) {

        $message = $_SESSION["signup_success"];

        echo '<h5 style="color:dodgerblue">' . $message . '</h5>';

        unset($_SESSION["signup_success"]);

    }

    if (isset($_SESSION["login_errors"])) {

        $errors = $_SESSION["login_errors"];

        foreach($errors as $error) {

            echo '<h5 style="color:firebrick">' . $error . '</h5>';

        }

        unset($_SESSION["login_errors"]);

    }

}