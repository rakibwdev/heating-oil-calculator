<!-- <div class="heating-oil-calculator">
    <h3><?php _e('Heating Oil Calculator', 'heating-oil-calculator'); ?></h3>
    
    <div class="calculator-fields">
        <div class="field-group">
            <label for="postal_code"><?php _e('Postal Code', 'heating-oil-calculator'); ?></label>
            <input type="text" id="postal_code" name="postal_code" placeholder="11111" required pattern="\d{5}" maxlength="5">
            <small><?php _e('Enter your 5-digit postal code', 'heating-oil-calculator'); ?></small>
        </div>
        
        <div class="field-group">
            <label for="liters"><?php _e('Delivery Quantity in Liters', 'heating-oil-calculator'); ?></label>
            <input type="number" id="liters" name="liters" min="1500" max="6000" step="100" value="2000" required>
            <div class="range-values">
                <span>Min: 1500L</span>
                <span>Max: 6000L</span>
            </div>
        </div>
        
        <div class="field-group">
            <label for="delivery_points"><?php _e('Delivery Points', 'heating-oil-calculator'); ?></label>
            <input type="number" id="delivery_points" name="delivery_points" min="1" max="5" value="1" required>
            <small><?php _e('Number of delivery locations', 'heating-oil-calculator'); ?></small>
        </div>
        
        <div class="price-preview">
            <div class="price-row">
                <span><?php _e('Total Price:', 'heating-oil-calculator'); ?></span>
                <strong id="total_price">€0.00</strong>
            </div>
            <div class="price-row">
                <span><?php _e('Price per 100L:', 'heating-oil-calculator'); ?></span>
                <span id="price_per_100l">€0.00</span>
            </div>
            <div class="price-row">
                <span><?php _e('Delivery Surcharge:', 'heating-oil-calculator'); ?></span>
                <span id="delivery_surcharge">€0.00</span>
            </div>
        </div>
        
        <div class="calculator-actions">
            <button type="button" id="calculate_price" class="button"><?php _e('Calculate Price', 'heating-oil-calculator'); ?></button>
        </div>
        
        <div class="error-messages" style="display:none; color: red;"></div>
        
        <input type="hidden" id="hoc_liters" name="hoc_liters" value="">
        <input type="hidden" id="hoc_delivery_points" name="hoc_delivery_points" value="">
        <input type="hidden" id="hoc_postal_code" name="hoc_postal_code" value="">
        <input type="hidden" id="hoc_total_price" name="hoc_total_price" value="">
    </div>
</div> -->