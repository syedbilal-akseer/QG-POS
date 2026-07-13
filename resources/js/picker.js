// easepick-directive.js

import { easepick, LockPlugin } from "@easepick/bundle";
import style from '@easepick/bundle/dist/index.css?url';
import easepickVariables from '../../resources/css/easepickVariables.css?url';
import dayjs from 'dayjs';

export default function (Alpine) {
    Alpine.directive('picker', (el, { expression }, { evaluate }) => {
        const options = evaluate(expression);

        const picker = new easepick.create({
            element: document.getElementById(el.id),
            readonly: true,
            zIndex: 1000,
            ...options,
            css: [
                style,
                easepickVariables
            ],
            plugins: [
                LockPlugin
            ],
            LockPlugin: {
                minDate: options.minDate === false ? null : new Date(),
                filter: (date) => {
                    const dayjsDate = dayjs(date); // Convert to dayjs instance

                    const dayName = dayjsDate.format('dddd'); // Get the day name

                    // Return true if the day is NOT in lockDays, otherwise return false
                    if (options.lockDays && Array.isArray(options.lockDays)) {
                        return options.lockDays.includes(dayName); // Lock if the day is in lockDays
                    }

                    // Default to allowing all days if lockDays is not defined
                    return false;
                },
            },
            setup(picker) {
                picker.on('view', (e) => {
                    const { view, date, target } = e.detail;
                });

                picker.on('select', (e) => {
                    // Dispatch the event with input ID in detail
                    window.dispatchEvent(
                        new CustomEvent('date-selected', {
                            detail: {
                                date: e.detail.date.format('DD/MM/YYYY'), // Selected date
                                inputId: el.id // Include the input ID
                            }
                        })
                    );
                });
            }
        });

        // Set initial theme from localStorage
        const darkMode = localStorage.getItem('darkMode') === 'true';
        picker.ui.container.dataset.theme = darkMode ? 'dark' : 'light';

        // Update theme on localStorage change in other tabs/windows
        window.addEventListener('storage', function () {
            const newDarkMode = localStorage.getItem('darkMode') === 'true';
            picker.ui.container.dataset.theme = newDarkMode ? 'dark' : 'light';
        });
    });
}
