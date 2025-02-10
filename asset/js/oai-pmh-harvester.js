'use strict';

(function ($) {
    $(document).ready(function() {

        /**
         * To allow form date time without time, use two date and time elements
         * and merge them visually.
         */
        const form = document.querySelector('form.oai-pmh-harvester');
        if (form) {
            let dateInput = form.querySelector('.field .datetime-date.datetime-from');
            let timeInput = form.querySelector('.field .datetime-time.datetime-from');
            if (dateInput && timeInput) {
                timeInput.closest('.field').remove();
                dateInput.insertAdjacentElement('afterend', timeInput);
            }
            dateInput = form.querySelector('.field .datetime-date.datetime-until');
            timeInput = form.querySelector('.field .datetime-time.datetime-until');
            if (dateInput && timeInput) {
                timeInput.closest('.field').remove();
                dateInput.insertAdjacentElement('afterend', timeInput);
            }
        }

    });
})(jQuery);
