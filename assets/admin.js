/**
 * Perfbase WordPress Plugin Admin JavaScript
 */

(function($) {
    'use strict';

    var PerfbaseAdmin = {
        init: function() {
            this.$page = $('.perfbase-admin-page');
            this.$form = $('.perfbase-settings-form');

            if (!this.$form.length) {
                return;
            }

            this.relocateSettingsNotice();
            this.bindEvents();
            this.initAdvancedOptions();
            this.updateApiKey();
            this.updateSampleRate();
            this.updateFlagsSummary();
            this.updateProfilingState();
        },

        bindEvents: function() {
            var self = this;

            this.$form.on('input change', 'input, textarea, select', function() {
                self.markDirty();
            });

            this.$form.on('submit', function() {
                self.$page.removeClass('has-unsaved-changes');
                self.$form.find('.perfbase-save-button').prop('disabled', true);
            });

            this.$form
                .find('input[name="perfbase_settings[api_key]"]')
                .on('input blur', function() {
                    self.updateApiKey();
                });

            this.$form
                .find('input[name="perfbase_settings[sample_rate]"]')
                .on('input', function() {
                    self.updateSampleRate();
                });

            this.$form
                .find('input[name="perfbase_settings[flags][]"]')
                .on('change', function() {
                    self.updateFlagsSummary();
                });

            this.$form
                .find('input[name="perfbase_settings[enabled]"]')
                .on('change', function() {
                    self.updateProfilingState();
                });

            this.$form
                .find('.perfbase-advanced-toggle')
                .on('click', function() {
                    self.toggleAdvancedOptions();
                });
        },

        relocateSettingsNotice: function() {
            var $slot = this.$page.find('.perfbase-notice-slot');
            var $notices;

            if (!$slot.length) {
                return;
            }

            $notices = $('.notice.notice-success').filter(function() {
                return $.trim($(this).text()).indexOf('Settings saved.') !== -1;
            });

            if (!$notices.length) {
                return;
            }

            $notices.slice(1).remove();
            $slot.empty().append($notices.first());
        },

        markDirty: function() {
            this.$page.addClass('has-unsaved-changes');
            this.$form.find('.perfbase-sticky-save').attr('aria-hidden', 'false');
        },

        initAdvancedOptions: function() {
            var isOpen = false;

            try {
                isOpen = window.localStorage.getItem('perfbaseAdvancedOptionsOpen') === '1';
            } catch (error) {
                isOpen = false;
            }

            this.setAdvancedOptionsState(isOpen);
        },

        toggleAdvancedOptions: function() {
            this.setAdvancedOptionsState(!this.$page.hasClass('is-showing-advanced'));
        },

        setAdvancedOptionsState: function(isOpen) {
            var $toggle = this.$form.find('.perfbase-advanced-toggle');
            var showLabel = $toggle.data('show-label') || 'Show advanced options';
            var hideLabel = $toggle.data('hide-label') || 'Hide advanced options';

            this.$page.toggleClass('is-showing-advanced', isOpen);
            $toggle
                .attr('aria-expanded', isOpen ? 'true' : 'false')
                .text(isOpen ? hideLabel : showLabel);

            try {
                window.localStorage.setItem('perfbaseAdvancedOptionsOpen', isOpen ? '1' : '0');
            } catch (error) {
                return;
            }
        },

        updateApiKey: function() {
            var $input = this.$form.find('input[name="perfbase_settings[api_key]"]');
            var $feedback = this.$form.find('.perfbase-api-key-feedback');
            var apiKey = $.trim($input.val());
            var hasStored = $input.data('has-stored') === 1 || $input.data('has-stored') === '1';
            var validation;

            if (!$feedback.length) {
                return;
            }

            if (apiKey === '') {
                if (hasStored) {
                    $feedback
                        .removeClass('is-error is-success')
                        .addClass('is-muted')
                        .text('Stored API key will be kept.');
                    $input.removeAttr('aria-invalid');
                    return;
                }

                $feedback
                    .removeClass('is-success is-muted')
                    .addClass('is-error')
                    .text('API key is required to submit traces.');
                $input.attr('aria-invalid', 'true');
                return;
            }

            validation = this.validateJwtShape(apiKey);
            if (!validation.valid) {
                $feedback
                    .removeClass('is-success is-muted')
                    .addClass('is-error')
                    .text(validation.message);
                $input.attr('aria-invalid', 'true');
                return;
            }

            $feedback
                .removeClass('is-error is-muted')
                .addClass('is-success')
                .text('API key format looks valid.');
            $input.removeAttr('aria-invalid');
        },

        validateJwtShape: function(value) {
            var parts = value.split('.');

            if (parts.length !== 3 || !parts[0] || !parts[1] || !parts[2]) {
                return {
                    valid: false,
                    message: 'API key must include three encoded sections.'
                };
            }

            if (!this.isBase64Url(parts[0]) || !this.isBase64Url(parts[1]) || !this.isBase64Url(parts[2])) {
                return {
                    valid: false,
                    message: 'API key contains unsupported characters.'
                };
            }

            if (!this.decodeJsonSegment(parts[0]) || !this.decodeJsonSegment(parts[1])) {
                return {
                    valid: false,
                    message: 'API key could not be decoded.'
                };
            }

            return { valid: true };
        },

        isBase64Url: function(segment) {
            return /^[A-Za-z0-9_-]+$/.test(segment);
        },

        decodeJsonSegment: function(segment) {
            var normalized = segment.replace(/-/g, '+').replace(/_/g, '/');
            var padding = normalized.length % 4;
            var decoded;

            if (padding) {
                normalized += new Array(5 - padding).join('=');
            }

            try {
                decoded = window.atob(normalized);
                return JSON.parse(decoded);
            } catch (error) {
                return null;
            }
        },

        updateSampleRate: function() {
            var $input = this.$form.find('input[name="perfbase_settings[sample_rate]"]');
            var $feedback = this.$form.find('.perfbase-sample-rate-feedback');
            var rate = parseFloat($input.val());

            if (!$feedback.length) {
                return;
            }

            if (isNaN(rate) || rate < 0 || rate > 1) {
                $feedback
                    .addClass('is-error')
                    .text('Sample rate must be between 0.0 and 1.0.');
                $input.attr('aria-invalid', 'true');
                return;
            }

            $feedback
                .removeClass('is-error')
                .text('Will profile ' + Math.round(rate * 100) + '% of requests.');
            $input.removeAttr('aria-invalid');
        },

        updateFlagsSummary: function() {
            var checked = this.$form.find('input[name="perfbase_settings[flags][]"]:checked').length;
            var total = this.$form.find('input[name="perfbase_settings[flags][]"]').length;
            var $summary = this.$form.find('.perfbase-flags-count');

            if (!$summary.length) {
                return;
            }

            $summary.text(checked + ' of ' + total + ' capabilities enabled');
        },

        updateProfilingState: function() {
            var enabled = this.$form.find('input[name="perfbase_settings[enabled]"]').is(':checked');
            this.$form.find('.perfbase-profiling-card').toggleClass('is-muted', !enabled);
        }
    };

    $(function() {
        PerfbaseAdmin.init();
    });

    window.PerfbaseAdmin = PerfbaseAdmin;
})(jQuery);
