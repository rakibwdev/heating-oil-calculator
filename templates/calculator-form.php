<?php
/**
 * Calculator Form Template
 * This file contains the HTML form for the heating oil calculator.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="heating-oil-calculator">
    <h3>Heating Oil Calculator</h3>
    <form id="heating-oil-form">
        <!-- Add form fields here -->
        <p>
            <label for="home-size">Home Size (sq ft):</label>
            <input type="number" id="home-size" name="home_size" required>
        </p>
        <p>
            <label for="heating-degree-days">Heating Degree Days:</label>
            <input type="number" id="heating-degree-days" name="heating_degree_days" required>
        </p>
        <p>
            <button type="submit">Calculate</button>
        </p>
    </form>
    <div id="calculator-result"></div>
</div>
