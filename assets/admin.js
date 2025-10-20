/**
 * Perfbase WordPress Plugin Admin JavaScript
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        PerfbaseAdmin.init();
    });

    var PerfbaseAdmin = {

        /**
         * Initialize admin functionality
         */
        init: function() {
            this.bindEvents();
            this.validateSettings();
            this.toggleAdvancedSettings();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Enable/disable profiling toggle
            $('input[name="perfbase_settings[enabled]"]').on('change', this.toggleProfiling);

            // API key validation
            $('input[name="perfbase_settings[api_key]"]').on('blur', this.validateApiKey);

            // Sample rate validation
            $('input[name="perfbase_settings[sample_rate]"]').on('input', this.validateSampleRate);

            // Advanced settings toggle
            $('.perfbase-advanced-toggle').on('click', this.toggleAdvancedSettings);

            // Flag checkboxes
            $('input[name="perfbase_settings[flags][]"]').on('change', this.updateFlags);

            // Test connection button
            $('.perfbase-test-connection').on('click', this.testConnection);
        },

        /**
         * Toggle profiling enabled/disabled
         */
        toggleProfiling: function() {
            var enabled = $(this).is(':checked');
            var $form = $(this).closest('form');

            if (enabled) {
                $form.find('.perfbase-profiling-options').show();
                $form.find('.perfbase-warning-disabled').hide();
            } else {
                $form.find('.perfbase-profiling-options').hide();
                $form.find('.perfbase-warning-disabled').show();
            }
        },

        /**
         * Validate API key format
         */
        validateApiKey: function() {
            var apiKey = $(this).val().trim();
            var $feedback = $(this).siblings('.perfbase-api-key-feedback');

            if (!$feedback.length) {
                $feedback = $('<div class="perfbase-api-key-feedback"></div>');
                $(this).after($feedback);
            }

            if (apiKey === '') {
                $feedback.html('<span style="color: #d63638;">API key is required</span>');
            } else if (apiKey.length < 32) {
                $feedback.html('<span style="color: #dba617;">API key seems too short</span>');
            } else {
                $feedback.html('<span style="color: #00a32a;">API key format looks valid</span>');
            }
        },

        /**
         * Validate sample rate
         */
        validateSampleRate: function() {
            var rate = parseFloat($(this).val());
            var $feedback = $(this).siblings('.perfbase-sample-rate-feedback');

            if (!$feedback.length) {
                $feedback = $('<div class="perfbase-sample-rate-feedback"></div>');
                $(this).after($feedback);
            }

            if (isNaN(rate) || rate < 0 || rate > 1) {
                $feedback.html('<span style="color: #d63638;">Sample rate must be between 0.0 and 1.0</span>');
                $(this).css('border-color', '#d63638');
            } else {
                var percentage = Math.round(rate * 100);
                $feedback.html('<span style="color: #00a32a;">Will profile ' + percentage + '% of requests</span>');
                $(this).css('border-color', '');
            }
        },

        /**
         * Toggle advanced settings visibility
         */
        toggleAdvancedSettings: function(e) {
            if (e) {
                e.preventDefault();
            }

            var $advanced = $('.perfbase-advanced-settings');
            var $toggle = $('.perfbase-advanced-toggle');

            if ($advanced.is(':visible')) {
                $advanced.hide();
                $toggle.text('Show Advanced Settings');
            } else {
                $advanced.show();
                $toggle.text('Hide Advanced Settings');
            }
        },

        /**
         * Update flags display
         */
        updateFlags: function() {
            var selectedFlags = [];
            var totalValue = 0;

            $('input[name="perfbase_settings[flags][]"]:checked').each(function() {
                var value = parseInt($(this).val());
                var label = $(this).parent().text().trim();
                selectedFlags.push(label);
                totalValue += value;
            });

            var $summary = $('.perfbase-flags-summary');
            if (!$summary.length) {
                $summary = $('<div class="perfbase-flags-summary" style="margin-top: 10px; padding: 10px; background: #f0f0f1; border-radius: 4px;"></div>');
                $('.perfbase-flags').append($summary);
            }

            if (selectedFlags.length > 0) {
                $summary.html(
                    '<strong>Selected features:</strong> ' + selectedFlags.join(', ') +
                    '<br><small>Flag value: ' + totalValue + '</small>'
                );
            } else {
                $summary.html('<em>No profiling features selected</em>');
            }
        },

        /**
         * Test API connection
         */
        testConnection: function(e) {
            e.preventDefault();

            var $button = $(this);
            var originalText = $button.text();
            var apiKey = $('input[name="perfbase_settings[api_key]"]').val();
            var apiUrl = $('input[name="perfbase_settings[api_url]"]').val();

            if (!apiKey) {
                alert('Please enter an API key first.');
                return;
            }

            $button.text('Testing...').prop('disabled', true);

            // This would typically make an AJAX request to test the connection
            // For now, we'll just simulate it
            setTimeout(function() {
                // Simulate connection test
                var success = Math.random() > 0.3; // 70% success rate for demo

                if (success) {
                    $button.text('✓ Connection successful').css('color', '#00a32a');
                } else {
                    $button.text('✗ Connection failed').css('color', '#d63638');
                }

                setTimeout(function() {
                    $button.text(originalText).css('color', '').prop('disabled', false);
                }, 3000);
            }, 2000);
        },

        /**
         * Validate all settings
         */
        validateSettings: function() {
            // Run initial validation
            $('input[name="perfbase_settings[api_key]"]').trigger('blur');
            $('input[name="perfbase_settings[sample_rate]"]').trigger('input');
            $('input[name="perfbase_settings[enabled]"]').trigger('change');

            // Update flags summary
            this.updateFlags();
        }
    };

    // Make PerfbaseAdmin globally available
    window.PerfbaseAdmin = PerfbaseAdmin;

})(jQuery);