/**
 * Leave Requests Widget (Preact + Hooks)
 * 
 * Widget for managing leave requests (animateurs submit, coordinateurs approve/reject).
 * 
 * @package MJ_Member
 */
(function() {
    'use strict';

    const { h, render, Fragment } = window.preact;
    const { useState, useEffect, useCallback, useMemo } = window.preactHooks;

    // Utility functions
    const formatDate = (dateStr) => {
        const date = new Date(dateStr);
        return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
    };

    const formatDateShort = (dateStr) => {
        const date = new Date(dateStr);
        return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    };

    const getDaysInMonth = (year, month) => new Date(year, month + 1, 0).getDate();
    const getFirstDayOfMonth = (year, month) => {
        const day = new Date(year, month, 1).getDay();
        // Convert JS getDay (0=Sunday) to our week format (0=Monday, 6=Sunday)
        return day === 0 ? 6 : day - 1;
    };

    const isWeekend = (year, month, day) => {
        const dayOfWeek = new Date(year, month, day).getDay();
        return dayOfWeek === 0 || dayOfWeek === 6;
    };

    const dateToString = (year, month, day) => {
        const m = String(month + 1).padStart(2, '0');
        const d = String(day).padStart(2, '0');
        return `${year}-${m}-${d}`;
    };

    const getMonthName = (month) => {
        const months = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 
                        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
        return months[month];
    };

    // API functions
    const api = {
        create: async (data, file) => {
            const formData = new FormData();
            formData.append('action', 'mj_leave_request_create');
            formData.append('nonce', mjLeaveRequests.nonce);
            formData.append('type_id', data.typeId);
            formData.append('dates', JSON.stringify(data.dates));
            formData.append('reason', data.reason || '');
            if (file) {
                formData.append('certificate', file);
            }
            const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
            return response.json();
        },
        cancel: async (requestId) => {
            const formData = new FormData();
            formData.append('action', 'mj_leave_request_cancel');
            formData.append('nonce', mjLeaveRequests.nonce);
            formData.append('request_id', requestId);
            const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
            return response.json();
        },
        approve: async (requestId, comment = '') => {
            const formData = new FormData();
            formData.append('action', 'mj_leave_request_approve');
            formData.append('nonce', mjLeaveRequests.nonce);
            formData.append('request_id', requestId);
            formData.append('comment', comment);
            const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
            return response.json();
        },
        reject: async (requestId, comment) => {
            const formData = new FormData();
            formData.append('action', 'mj_leave_request_reject');
            formData.append('nonce', mjLeaveRequests.nonce);
            formData.append('request_id', requestId);
            formData.append('comment', comment);
            const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
            return response.json();
        },
        refresh: async (year = null) => {
            const formData = new FormData();
            formData.append('action', 'mj_leave_request_get');
            formData.append('nonce', mjLeaveRequests.nonce);
            if (year) {
                formData.append('year', year);
            }
            const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
            return response.json();
        },
        getByMember: async (memberId, year = null) => {
            const formData = new FormData();
            formData.append('action', 'mj_leave_request_by_member');
            formData.append('nonce', mjLeaveRequests.nonce);
            formData.append('member_id', memberId);
            if (year) {
                formData.append('year', year);
            }
            const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
            return response.json();
        },
        delete: async (requestId) => {
            const formData = new FormData();
            formData.append('action', 'mj_leave_request_delete');
            formData.append('nonce', mjLeaveRequests.nonce);
            formData.append('request_id', requestId);
            const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
            return response.json();
        }
    };

    // Calendar Picker Component
    function CalendarPicker({ selectedDates, onToggleDate, minDate, workSchedule, reservedDates, types }) {
        const today = new Date();
        const [viewYear, setViewYear] = useState(today.getFullYear());
        const [viewMonth, setViewMonth] = useState(today.getMonth());
        const weekdays = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];
        const i18n = mjLeaveRequests.i18n;
        const daysInMonth = getDaysInMonth(viewYear, viewMonth);
        const firstDay = getFirstDayOfMonth(viewYear, viewMonth);

        // Map day name strings to JS getDay() numbers (0=Sunday, 1=Monday, etc.)
        const dayNameToJs = {
            sunday: 0,
            monday: 1,
            tuesday: 2,
            wednesday: 3,
            thursday: 4,
            friday: 5,
            saturday: 6
        };

        // Get working days from schedule (array of day numbers 0-6, where 0=Sunday, 1=Monday, etc.)
        const workingDays = useMemo(() => {
            if (!workSchedule || !workSchedule.schedule) return null; // null means no restriction
            const days = new Set();
            workSchedule.schedule.forEach(entry => {
                if (entry.day !== undefined) {
                    // day can be stored as string ('monday') or number
                    if (typeof entry.day === 'string') {
                        const jsDay = dayNameToJs[entry.day.toLowerCase()];
                        if (jsDay !== undefined) {
                            days.add(jsDay);
                        }
                    } else {
                        // Numeric: 1=Monday to 7=Sunday, convert to JS (0=Sunday)
                        const jsDay = entry.day === 7 ? 0 : entry.day;
                        days.add(jsDay);
                    }
                }
            });
            return days.size > 0 ? days : null;
        }, [workSchedule]);

        // Check if a date is within the work schedule period
        const isWithinSchedulePeriod = useCallback((dateStr) => {
            if (!workSchedule) return true;
            if (workSchedule.startDate && dateStr < workSchedule.startDate) return false;
            if (workSchedule.endDate && dateStr > workSchedule.endDate) return false;
            return true;
        }, [workSchedule]);

        const prevMonth = () => {
            if (viewMonth === 0) {
                setViewYear(viewYear - 1);
                setViewMonth(11);
            } else {
                setViewMonth(viewMonth - 1);
            }
        };

        const nextMonth = () => {
            if (viewMonth === 11) {
                setViewYear(viewYear + 1);
                setViewMonth(0);
            } else {
                setViewMonth(viewMonth + 1);
            }
        };

        const days = [];
        // Empty cells for days before first day of month
        for (let i = 0; i < firstDay; i++) {
            days.push(h('div', { class: 'mj-leave-requests__calendar-day mj-leave-requests__calendar-day--other-month', key: `empty-${i}` }));
        }

        // Days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = dateToString(viewYear, viewMonth, day);
            const jsDate = new Date(viewYear, viewMonth, day);
            const dayOfWeek = jsDate.getDay(); // 0=Sunday, 1=Monday, etc.
            
            const isSelected = selectedDates.includes(dateStr);
            const isToday = today.getFullYear() === viewYear && today.getMonth() === viewMonth && today.getDate() === day;
            const isPast = jsDate < new Date(today.toDateString());
            const weekend = dayOfWeek === 0 || dayOfWeek === 6;
            
            // Check if this is a working day according to schedule
            const isWorkingDay = workingDays === null || workingDays.has(dayOfWeek);
            const inSchedulePeriod = isWithinSchedulePeriod(dateStr);
            
            // Check if date is already reserved (has an existing leave request)
            const reserved = reservedDates ? reservedDates[dateStr] : null;
            const isReserved = reserved && !isSelected; // Don't block if currently selected
            
            // Find the type for color
            const reservedType = reserved ? types.find(t => t.id === reserved.type_id) : null;
            const reservedColor = reservedType?.color || null;
            const isPending = reserved?.status === 'pending';

            // Determine if day is disabled
            const isDisabled = isPast || !isWorkingDay || !inSchedulePeriod || isReserved;

            let className = 'mj-leave-requests__calendar-day';
            if (isSelected) className += ' mj-leave-requests__calendar-day--selected';
            if (isToday) className += ' mj-leave-requests__calendar-day--today';
            if (weekend) className += ' mj-leave-requests__calendar-day--weekend';
            if (!isWorkingDay && !weekend) className += ' mj-leave-requests__calendar-day--not-working';
            if (isReserved) className += ' mj-leave-requests__calendar-day--reserved';

            // Style for reserved dates
            const style = {};
            if (isReserved && reservedColor) {
                style.backgroundColor = reservedColor;
                style.color = '#fff';
                style.borderColor = reservedColor;
                if (isPending) {
                    style.opacity = '0.6';
                }
            }

            days.push(
                h('button', {
                    type: 'button',
                    class: className,
                    style,
                    disabled: isDisabled,
                    onClick: () => !isDisabled && onToggleDate(dateStr),
                    title: isReserved ? (reservedType?.name || 'Réservé') : (!isWorkingDay ? 'Jour non travaillé' : ''),
                    key: dateStr
                }, day)
            );
        }
        
        return h('div', { class: 'mj-leave-requests__calendar' },
            h('div', { class: 'mj-leave-requests__calendar-header' },
                h('div', { class: 'mj-leave-requests__calendar-nav' },
                    h('button', { type: 'button', class: 'mj-leave-requests__calendar-nav-btn', onClick: prevMonth }, '‹')
                ),
                h('span', { class: 'mj-leave-requests__calendar-title' }, `${i18n.months[viewMonth]} ${viewYear}`),
                h('div', { class: 'mj-leave-requests__calendar-nav' },
                    h('button', { type: 'button', class: 'mj-leave-requests__calendar-nav-btn', onClick: nextMonth }, '›')
                )
            ),
            h('div', { class: 'mj-leave-requests__calendar-weekdays' },
                weekdays.map((day, i) => h('div', { class: 'mj-leave-requests__calendar-weekday', key: i }, day))
            ),
            h('div', { class: 'mj-leave-requests__calendar-days' }, days)
        );
    }

    

    // Single Month Calendar for Overview (read-only with colored leave days)
    function OverviewMonth({ year, month, requests, types, compact }) {
        const today = new Date();
        const daysInMonth = getDaysInMonth(year, month);
        const firstDay = getFirstDayOfMonth(year, month);
        const weekdays = ['L', 'M', 'M', 'J', 'V', 'S', 'D'];

        // Build a map of dates to leave types for coloring
        const dateToLeaveType = useMemo(() => {
            const map = {};
            requests.forEach(req => {
                if (req.status === 'rejected') return; // Don't show rejected
                const dates = req.dates_array || (typeof req.dates === 'string' ? JSON.parse(req.dates || '[]') : req.dates) || [];
                dates.forEach(dateStr => {
                    if (!map[dateStr]) {
                        map[dateStr] = [];
                    }
                    const type = types.find(t => t.id === parseInt(req.type_id));
                    map[dateStr].push({
                        color: type?.color || req.type_color || '#6c757d',
                        status: req.status,
                        typeSlug: type?.slug || req.type_slug || 'unknown'
                    });
                });
            });
            return map;
        }, [requests, types]);

        const days = [];
        // Empty cells before first day
        for (let i = 0; i < firstDay; i++) {
            days.push(h('div', { class: 'mj-leave-requests__overview-day mj-leave-requests__overview-day--empty', key: `empty-${i}` }));
        }

        // Days of the month
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = dateToString(year, month, day);
            const leaves = dateToLeaveType[dateStr] || [];
            const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
            const weekend = isWeekend(year, month, day);

            let className = 'mj-leave-requests__overview-day';
            if (isToday) className += ' mj-leave-requests__overview-day--today';
            if (weekend) className += ' mj-leave-requests__overview-day--weekend';
            if (leaves.length > 0) className += ' mj-leave-requests__overview-day--has-leave';

            // Get primary leave color (first one)
            const primaryLeave = leaves.find(l => l.status === 'approved') || leaves[0];
            const bgColor = primaryLeave ? primaryLeave.color : null;
            const isPending = primaryLeave && primaryLeave.status === 'pending';

            const style = bgColor ? { backgroundColor: bgColor, color: '#fff' } : {};
            if (isPending) {
                style.opacity = '0.6';
            }

            days.push(
                h('div', {
                    class: className,
                    style,
                    key: dateStr,
                    title: leaves.length > 0 ? leaves.map(l => l.typeSlug).join(', ') : ''
                }, 
                    day,
                    leaves.length > 1 && h('span', { class: 'mj-leave-requests__overview-multi' })
                )
            );
        }

        return h('div', { class: `mj-leave-requests__overview-month ${compact ? 'mj-leave-requests__overview-month--compact' : ''}` },
            h('div', { class: 'mj-leave-requests__overview-month-title' }, 
                `${getMonthName(month)} ${year}`
            ),
            h('div', { class: 'mj-leave-requests__overview-weekdays' },
                weekdays.map((day, i) => h('div', { class: 'mj-leave-requests__overview-weekday', key: i }, day))
            ),
            h('div', { class: 'mj-leave-requests__overview-days' }, days)
        );
    }

    // Calendar Overview Component - 3 months side by side
    function CalendarOverview({ requests, types, onDelete, selectedYear, isCoordinator, onApprove, onReject, onRejectModal }) {
        const today = new Date();
        const [startMonth, setStartMonth] = useState(selectedYear !== today.getFullYear() ? 0 : today.getMonth());
        const [startYear, setStartYear] = useState(selectedYear || today.getFullYear());
        const i18n = mjLeaveRequests.i18n;

        // Reset calendar when selected year changes
        useEffect(() => {
            if (selectedYear) {
                setStartYear(selectedYear);
                setStartMonth(0); // January of the selected year
            }
        }, [selectedYear]);

        // Get requests for visible period
        const getMonthRequests = useCallback((year, month) => {
            const monthStart = dateToString(year, month, 1);
            const monthEnd = dateToString(year, month, getDaysInMonth(year, month));
            
            return requests.filter(req => {
                const dates = req.dates_array || (typeof req.dates === 'string' ? JSON.parse(req.dates || '[]') : req.dates) || [];
                return dates.some(dateStr => dateStr >= monthStart && dateStr <= monthEnd);
            });
        }, [requests]);

        // Get 3 months data
        const months = useMemo(() => {
            const result = [];
            let y = startYear;
            let m = startMonth;
            for (let i = 0; i < 3; i++) {
                result.push({ year: y, month: m });
                m++;
                if (m > 11) {
                    m = 0;
                    y++;
                }
            }
            return result;
        }, [startYear, startMonth]);

        // Get requests for the 3-month period for the list below
        const periodRequests = useMemo(() => {
            const firstMonth = months[0];
            const lastMonth = months[2];
            const periodStart = dateToString(firstMonth.year, firstMonth.month, 1);
            const periodEnd = dateToString(lastMonth.year, lastMonth.month, getDaysInMonth(lastMonth.year, lastMonth.month));
            
            return requests.filter(req => {
                const dates = req.dates_array || (typeof req.dates === 'string' ? JSON.parse(req.dates || '[]') : req.dates) || [];
                return dates.some(dateStr => dateStr >= periodStart && dateStr <= periodEnd);
            }).sort((a, b) => {
                const datesA = a.dates_array || (typeof a.dates === 'string' ? JSON.parse(a.dates || '[]') : a.dates) || [];
                const datesB = b.dates_array || (typeof b.dates === 'string' ? JSON.parse(b.dates || '[]') : b.dates) || [];
                return (datesA[0] || '').localeCompare(datesB[0] || '');
            });
        }, [requests, months]);

        const prevMonths = () => {
            let m = startMonth - 3;
            let y = startYear;
            while (m < 0) {
                m += 12;
                y--;
            }
            setStartMonth(m);
            setStartYear(y);
        };

        const nextMonths = () => {
            let m = startMonth + 3;
            let y = startYear;
            while (m > 11) {
                m -= 12;
                y++;
            }
            setStartMonth(m);
            setStartYear(y);
        };

        const goToToday = () => {
            setStartMonth(today.getMonth());
            setStartYear(today.getFullYear());
        };

        return h('div', { class: 'mj-leave-requests__overview' },
            // Navigation
            h('div', { class: 'mj-leave-requests__overview-nav' },
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__overview-nav-btn', 
                    onClick: prevMonths,
                    title: 'Mois précédents'
                }, '‹‹'),
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__overview-nav-btn mj-leave-requests__overview-nav-btn--today', 
                    onClick: goToToday
                }, i18n.today || 'Aujourd\'hui'),
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__overview-nav-btn', 
                    onClick: nextMonths,
                    title: 'Mois suivants'
                }, '››')
            ),

            // Calendars grid
            h('div', { class: 'mj-leave-requests__overview-calendars' },
                months.map(({ year, month }) =>
                    h(OverviewMonth, {
                        key: `${year}-${month}`,
                        year,
                        month,
                        requests: getMonthRequests(year, month),
                        types,
                        compact: false
                    })
                )
            ),

            // Legend
            h('div', { class: 'mj-leave-requests__overview-legend' },
                types.map(type =>
                    h('span', { class: 'mj-leave-requests__overview-legend-item', key: type.id },
                        h('span', { 
                            class: 'mj-leave-requests__overview-legend-color',
                            style: { backgroundColor: type.color }
                        }),
                        type.name
                    )
                )
            ),

            // Requests list for the period
            periodRequests.length > 0 && h('div', { class: 'mj-leave-requests__overview-requests' },
                h('h4', { class: 'mj-leave-requests__overview-requests-title' }, 
                    i18n.requestsInPeriod || 'Demandes sur cette période'
                ),
                h('div', { class: 'mj-leave-requests__overview-requests-list' },
                    periodRequests.map(req => {
                        const type = types.find(t => t.id === parseInt(req.type_id));
                        const dates = req.dates_array || (typeof req.dates === 'string' ? JSON.parse(req.dates || '[]') : req.dates) || [];
                        const dateCount = dates.length;
                        const dateDisplay = dateCount === 1 
                            ? formatDateShort(dates[0])
                            : `${formatDateShort(dates[0])} → ${formatDateShort(dates[dateCount - 1])}`;
                        const statusLabel = i18n[req.status] || req.status;

                        // Check if request can be deleted (coordinator: any status; self: pending/approved in future)
                        const todayStr = new Date().toISOString().slice(0, 10);
                        const allDatesInFuture = dates.every(d => d > todayStr);
                        const canDelete = isCoordinator
                            ? true
                            : (req.status === 'pending' || (req.status === 'approved' && allDatesInFuture));
                        
                        // Check if is sick leave with certificate
                        const isSickLeave = type && type.slug === 'sick';
                        const hasCertificate = req.certificate_file && req.certificate_file.length > 0;

                        return h('div', { 
                            class: 'mj-leave-requests__overview-request',
                            key: req.id 
                        },
                            h('span', { 
                                class: 'mj-leave-requests__overview-request-color',
                                style: { backgroundColor: type?.color || req.type_color || '#6c757d' }
                            }),
                            h('span', { class: 'mj-leave-requests__overview-request-dates' }, dateDisplay),
                            h('span', { class: 'mj-leave-requests__overview-request-type' }, type?.name || req.type_name),
                            h('span', { 
                                class: `mj-leave-requests__overview-request-status mj-leave-requests__status--${req.status}` 
                            }, statusLabel),
                            dateCount > 1 && h('span', { class: 'mj-leave-requests__overview-request-days' }, 
                                `${dateCount} ${dateCount > 1 ? i18n.days : i18n.day}`
                            ),
                            req.reason && h('span', { 
                                class: 'mj-leave-requests__overview-request-note',
                                title: req.reason
                            }, req.reason),
                            req.status === 'rejected' && req.reviewer_comment && h('span', {
                                class: 'mj-leave-requests__overview-request-comment mj-leave-requests__overview-request-comment--rejected',
                                title: req.reviewer_comment
                            }, req.reviewer_comment),
                            req.status === 'approved' && req.reviewer_comment && req.reviewer_comment !== 'Approuvé automatiquement' && h('span', {
                                class: 'mj-leave-requests__overview-request-comment mj-leave-requests__overview-request-comment--approved',
                                title: req.reviewer_comment
                            }, req.reviewer_comment),
                            isSickLeave && hasCertificate && h('a', {
                                href: `${mjLeaveRequests.ajaxUrl}?action=mj_leave_request_certificate&request_id=${req.id}&nonce=${mjLeaveRequests.nonce}`,
                                download: true,
                                target: '_blank',
                                class: 'mj-leave-requests__overview-request-certificate',
                                title: i18n.downloadCertificate || 'Télécharger certificat'
                            }, '📥'),
                            isCoordinator && req.status === 'pending' && h('button', {
                                type: 'button',
                                class: 'mj-leave-requests__btn mj-leave-requests__btn--success mj-leave-requests__btn--xs',
                                onClick: () => onApprove && onApprove(req),
                                title: i18n.approve
                            }, '✓'),
                            isCoordinator && req.status === 'pending' && h('button', {
                                type: 'button',
                                class: 'mj-leave-requests__btn mj-leave-requests__btn--danger mj-leave-requests__btn--xs',
                                onClick: () => onRejectModal && onRejectModal(req),
                                title: i18n.reject
                            }, '✕'),
                            onDelete && canDelete && h('button', {
                                type: 'button',
                                class: 'mj-leave-requests__overview-request-delete',
                                onClick: () => onDelete(req.id),
                                title: i18n.delete || 'Supprimer'
                            }, '×')
                        );
                    })
                )
            )
        );
    }

    // Modal Component
    function Modal({ isOpen, onClose, title, children, footer }) {
        if (!isOpen) return null;

        return h('div', { class: 'mj-leave-requests__modal-overlay', onClick: (e) => e.target === e.currentTarget && onClose() },
            h('div', { class: 'mj-leave-requests__modal' },
                h('div', { class: 'mj-leave-requests__modal-header' },
                    h('h2', { class: 'mj-leave-requests__modal-title' }, title),
                    h('button', { type: 'button', class: 'mj-leave-requests__modal-close', onClick: onClose }, '×')
                ),
                h('div', { class: 'mj-leave-requests__modal-body' }, children),
                footer && h('div', { class: 'mj-leave-requests__modal-footer' }, footer)
            )
        );
    }

    // Leave Request Form Component
    function LeaveRequestForm({ onClose, onSuccess, types, quotas, usage, workSchedule, reservedDates }) {
        const i18n = mjLeaveRequests.i18n;
        const [typeId, setTypeId] = useState(null);
        const [dates, setDates] = useState([]);
        const [reason, setReason] = useState('');
        const [file, setFile] = useState(null);
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');

        const selectedType = useMemo(() => types.find(t => t.id === typeId), [types, typeId]);
        const requiresDocument = selectedType?.requires_document;
        const requiresValidation = selectedType?.requires_validation;

        const toggleDate = (dateStr) => {
            setDates(prev => 
                prev.includes(dateStr) 
                    ? prev.filter(d => d !== dateStr)
                    : [...prev, dateStr].sort()
            );
        };

        const removeDate = (dateStr) => {
            setDates(prev => prev.filter(d => d !== dateStr));
        };

        const handleFileChange = (e) => {
            setFile(e.target.files[0] || null);
        };

        const handleSubmit = async () => {
            setError('');

            if (!typeId) {
                setError(i18n.selectType);
                return;
            }
            if (dates.length === 0) {
                setError(i18n.selectDates);
                return;
            }
            if (requiresDocument && !file) {
                setError(i18n.certificateRequired);
                return;
            }

            setLoading(true);
            try {
                const result = await api.create({ typeId, dates, reason }, file);
                if (result.success) {
                    onSuccess(result.data);
                } else {
                    setError(result.data?.message || i18n.error);
                }
            } catch (e) {
                setError(i18n.error);
            }
            setLoading(false);
        };

        return h(Fragment, null,
            // Type selector
            h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label mj-leave-requests__form-label--required' }, i18n.selectType),
                h('div', { class: 'mj-leave-requests__type-selector' },
                    types.map(type => {
                        const quota = quotas?.[type.slug] ?? null;
                        const used = usage?.[type.slug] ?? 0;
                        const remaining = quota !== null ? Math.max(0, quota - used) : null;
                        const isSelected = typeId === type.id;
                        const style = isSelected ? {
                            borderColor: type.color,
                            backgroundColor: type.color.substring(0, 7) + '15'
                        } : {};
                        
                        return h('label', { 
                            class: `mj-leave-requests__type-option ${isSelected ? 'mj-leave-requests__type-option--selected' : ''}`,
                            style,
                            key: type.id 
                        },
                            h('input', { 
                                type: 'radio', 
                                name: 'leave_type', 
                                value: type.id,
                                checked: isSelected,
                                onChange: () => setTypeId(type.id)
                            }),
                            h('span', { class: 'mj-leave-requests__type-option-name' }, type.name),
                            quota !== null && h('span', { class: 'mj-leave-requests__type-option-quota' }, 
                                `${used} / ${quota}`
                            ),
                            !type.requires_validation && h('span', { class: 'mj-leave-requests__type-option-auto' }, i18n.autoApproved)
                        );
                    })
                )
            ),
            // Calendar
            h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label mj-leave-requests__form-label--required' }, i18n.selectDates),
                h(CalendarPicker, { 
                    selectedDates: dates, 
                    onToggleDate: toggleDate,
                    workSchedule,
                    reservedDates,
                    types
                }),
                dates.length > 0 
                    ? h('div', { class: 'mj-leave-requests__selected-dates' },
                        dates.map(d => 
                            h('span', { class: 'mj-leave-requests__selected-date', key: d },
                                formatDateShort(d),
                                h('button', { 
                                    type: 'button', 
                                    class: 'mj-leave-requests__selected-date-remove',
                                    onClick: () => removeDate(d)
                                }, '×')
                            )
                        )
                    )
                    : h('p', { class: 'mj-leave-requests__form-hint' }, i18n.noDatesSelected)
            ),
            // Reason
            h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label' }, i18n.reason),
                h('textarea', { 
                    class: 'mj-leave-requests__form-textarea',
                    value: reason,
                    onInput: (e) => setReason(e.target.value),
                    rows: 3
                })
            ),
            // Certificate upload (if required)
            requiresDocument && h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label mj-leave-requests__form-label--required' }, i18n.certificate),
                h('div', { class: 'mj-leave-requests__file-upload' },
                    h('input', { 
                        type: 'file', 
                        accept: '.pdf,.jpg,.jpeg,.png,.gif',
                        onChange: handleFileChange 
                    }),
                    h('div', { class: 'mj-leave-requests__file-upload-icon' }, '📄'),
                    h('div', { class: 'mj-leave-requests__file-upload-text' }, i18n.uploadCertificate),
                    h('div', { class: 'mj-leave-requests__file-upload-hint' }, 'PDF, JPG, PNG, GIF')
                ),
                file && h('div', { class: 'mj-leave-requests__file-preview' },
                    h('span', { class: 'mj-leave-requests__file-preview-name' }, file.name),
                    h('button', { 
                        type: 'button', 
                        class: 'mj-leave-requests__file-preview-remove',
                        onClick: () => setFile(null)
                    }, '×')
                )
            ),
            // Error
            error && h('p', { class: 'mj-leave-requests__form-error' }, error),
            // Footer
            h('div', { class: 'mj-leave-requests__modal-footer', style: { marginTop: '1rem', padding: 0, background: 'none', border: 'none' } },
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--secondary',
                    onClick: onClose 
                }, i18n.cancel),
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--primary',
                    onClick: handleSubmit,
                    disabled: loading
                }, loading ? '...' : i18n.submit)
            )
        );
    }

    // Request Item Component
    function RequestItem({ request, types, onCancel, onApprove, onReject, onDelete, isCoordinator, showMember }) {
        const i18n = mjLeaveRequests.i18n;
        const type = types.find(t => t.id === parseInt(request.type_id)) || { name: 'Congé', slug: 'unknown' };
        const dates = JSON.parse(request.dates || '[]');
        const dateCount = dates.length;

        const statusClass = `mj-leave-requests__status mj-leave-requests__status--${request.status}`;
        const statusLabel = i18n[request.status] || request.status;

        const dateDisplay = dateCount === 1 
            ? formatDate(dates[0])
            : `${formatDateShort(dates[0])} - ${formatDateShort(dates[dateCount - 1])} (${dateCount} jours)`;

        return h('div', { class: 'mj-leave-requests__item' },
            h('div', { class: `mj-leave-requests__item-type mj-leave-requests__item-type--${type.slug}` }),
            h('div', { class: 'mj-leave-requests__item-content' },
                h('div', { class: 'mj-leave-requests__item-header' },
                    showMember && h('div', { class: 'mj-leave-requests__item-member' },
                        request.member_avatar && h('img', { 
                            src: request.member_avatar, 
                            alt: request.member_name || '',
                            class: 'mj-leave-requests__item-avatar'
                        }),
                        h('strong', { class: 'mj-leave-requests__item-member-name' }, request.member_name || 'Membre')
                    ),
                    h('span', { class: 'mj-leave-requests__item-title' }, type.name),
                    h('span', { class: statusClass }, statusLabel)
                ),
                h('div', { class: 'mj-leave-requests__item-dates' }, dateDisplay),
                request.reason && h('div', { class: 'mj-leave-requests__item-reason' }, request.reason),
                request.certificate_file && h('div', { class: 'mj-leave-requests__item-certificate' },
                    h('a', { 
                        href: `${mjLeaveRequests.ajaxUrl}?action=mj_leave_request_certificate&request_id=${request.id}&nonce=${mjLeaveRequests.nonce}`, 
                        target: '_blank',
                        class: 'mj-leave-requests__item-certificate-link' 
                    }, 
                        h('span', { class: 'mj-leave-requests__item-certificate-icon' }, '📎'),
                        h('span', { class: 'mj-leave-requests__item-certificate-text' }, 'Certificat médical')
                    )
                ),
                request.status === 'rejected' && request.reviewer_comment && 
                    h('div', { class: 'mj-leave-requests__rejection-comment' }, request.reviewer_comment)
            ),
            h('div', { class: 'mj-leave-requests__item-actions' },
                // Animateur can cancel pending requests
                !isCoordinator && request.status === 'pending' && h('button', {
                    type: 'button',
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--outline mj-leave-requests__btn--sm',
                    onClick: () => onCancel(request.id)
                }, i18n.cancel),
                // Coordinator can approve/reject pending requests
                isCoordinator && request.status === 'pending' && h(Fragment, null,
                    h('button', {
                        type: 'button',
                        class: 'mj-leave-requests__btn mj-leave-requests__btn--success mj-leave-requests__btn--sm',
                        onClick: () => onApprove(request)
                    }, i18n.approve),
                    h('button', {
                        type: 'button',
                        class: 'mj-leave-requests__btn mj-leave-requests__btn--danger mj-leave-requests__btn--sm',
                        onClick: () => onReject(request)
                    }, i18n.reject)
                ),
                // Coordinator can delete any request
                isCoordinator && onDelete && h('button', {
                    type: 'button',
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--outline mj-leave-requests__btn--sm',
                    onClick: () => onDelete(request.id),
                    style: { marginLeft: '4px' }
                }, i18n.delete || 'Supprimer')
            )
        );
    }

    // Reject Modal Component
    function RejectModal({ request, onClose, onConfirm }) {
        const i18n = mjLeaveRequests.i18n;
        const [comment, setComment] = useState('');
        const [error, setError] = useState('');
        const [loading, setLoading] = useState(false);

        const handleConfirm = async () => {
            if (!comment.trim()) {
                setError(i18n.rejectionReasonRequired);
                return;
            }
            setLoading(true);
            await onConfirm(request.id, comment);
            setLoading(false);
        };

        return h(Modal, { 
            isOpen: true, 
            onClose, 
            title: i18n.reject,
            footer: h(Fragment, null,
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--secondary',
                    onClick: onClose 
                }, i18n.cancel),
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--danger',
                    onClick: handleConfirm,
                    disabled: loading
                }, loading ? '...' : i18n.reject)
            )
        },
            h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label mj-leave-requests__form-label--required' }, i18n.rejectionReason),
                h('textarea', { 
                    class: 'mj-leave-requests__form-textarea',
                    value: comment,
                    onInput: (e) => setComment(e.target.value),
                    rows: 3
                }),
                error && h('p', { class: 'mj-leave-requests__form-error' }, error)
            )
        );
    }

    // Quota Card Component
    function QuotaCard({ type, quota, used }) {
        const remaining = Math.max(0, quota - used);
        const i18n = mjLeaveRequests.i18n;

        return h('div', { class: `mj-leave-requests__quota-card mj-leave-requests__quota-card--${type.slug}` },
            h('span', { class: 'mj-leave-requests__quota-label' }, type.name),
            h('span', { class: 'mj-leave-requests__quota-value' }, 
                `${used}`,
                h('small', null, ` / ${quota}`)
            )
        );
    }

    // Coordinator View Component - Interface with tabs per animator
    function CoordinatorView({ types, animateurs, pendingRequests, onApprove, onReject, onRefresh, initialMemberId, selectedYear }) {
        const i18n = mjLeaveRequests.i18n;
        const currentYear = new Date().getFullYear();
        const [selectedAnimateur, setSelectedAnimateur] = useState(initialMemberId || null);
        const [memberData, setMemberData] = useState(null);
        const [loading, setLoading] = useState(false);
        const [rejectingRequest, setRejectingRequest] = useState(null);
        const [viewYear, setViewYear] = useState(selectedYear || currentYear);
        const [showCreateForm, setShowCreateForm] = useState(false);
        const availableYears = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1];

        // Count pending per animateur
        const pendingCounts = useMemo(() => {
            const counts = {};
            pendingRequests.forEach(r => {
                const mid = parseInt(r.member_id);
                counts[mid] = (counts[mid] || 0) + 1;
            });
            return counts;
        }, [pendingRequests]);

        // Sort animateurs: those with pending first, then alphabetically
        const sortedAnimateurs = useMemo(() => {
            return [...animateurs].sort((a, b) => {
                const aPending = pendingCounts[a.id] || 0;
                const bPending = pendingCounts[b.id] || 0;
                if (bPending !== aPending) return bPending - aPending;
                return a.name.localeCompare(b.name);
            });
        }, [animateurs, pendingCounts]);

        // Load member data when selected or year changes
        useEffect(() => {
            if (!selectedAnimateur) {
                setMemberData(null);
                return;
            }
            loadMemberData(selectedAnimateur, viewYear);
        }, [selectedAnimateur, viewYear]);

        const loadMemberData = async (memberId, year) => {
            setLoading(true);
            try {
                const result = await api.getByMember(memberId, year);
                if (result.success) {
                    setMemberData(result.data);
                }
            } catch (e) {
                console.error('Failed to load member data', e);
            }
            setLoading(false);
        };

        const handleApprove = async (request) => {
            const result = await api.approve(request.id);
            if (result.success) {
                loadMemberData(selectedAnimateur, viewYear);
                onRefresh();
            }
        };

        const handleReject = async (requestId, comment) => {
            const result = await api.reject(requestId, comment);
            if (result.success) {
                setRejectingRequest(null);
                loadMemberData(selectedAnimateur, viewYear);
                onRefresh();
            }
        };

        const handleDelete = async (requestId) => {
            if (!confirm(i18n.confirmDelete || 'Êtes-vous sûr de vouloir supprimer cette demande ?')) {
                return;
            }
            const result = await api.delete(requestId);
            if (result.success) {
                loadMemberData(selectedAnimateur, viewYear);
                onRefresh();
            } else {
                alert(result.data?.message || i18n.error);
            }
        };

        return h('div', { class: 'mj-leave-requests__coordinator' },
            // Animateurs sidebar
            h('div', { class: 'mj-leave-requests__coordinator-sidebar' },
                h('h2', { class: 'mj-leave-requests__coordinator-sidebar-title' }, i18n.animateurs || 'Animateurs'),
                h('div', { class: 'mj-leave-requests__coordinator-animateurs' },
                    sortedAnimateurs.map(anim => {
                        const pendingCount = pendingCounts[anim.id] || 0;
                        const isActive = selectedAnimateur === anim.id;
                        return h('button', {
                            key: anim.id,
                            type: 'button',
                            class: `mj-leave-requests__coordinator-animateur ${isActive ? 'mj-leave-requests__coordinator-animateur--active' : ''} ${pendingCount > 0 ? 'mj-leave-requests__coordinator-animateur--pending' : ''}`,
                            onClick: () => setSelectedAnimateur(anim.id)
                        },
                            h('span', { class: 'mj-leave-requests__coordinator-animateur-name' }, anim.name),
                            pendingCount > 0 && h('span', { class: 'mj-leave-requests__coordinator-animateur-badge' }, pendingCount)
                        );
                    })
                )
            ),

            // Member details panel
            h('div', { class: 'mj-leave-requests__coordinator-main' },
                !selectedAnimateur ? h('div', { class: 'mj-leave-requests__coordinator-empty' },
                    h('p', null, i18n.selectAnimateur || 'Sélectionnez un animateur pour voir ses demandes')
                ) :
                loading ? h('div', { class: 'mj-leave-requests__loading' }, h('div', { class: 'mj-leave-requests__spinner' })) :
                memberData ? h(Fragment, null,
                    // Member info
                    h('div', { class: 'mj-leave-requests__coordinator-member-header' },
                        h('h2', null, memberData.member?.name || 'Animateur'),
                        h('span', { class: 'mj-leave-requests__coordinator-role' }, memberData.member?.role),
                        h('button', {
                            type: 'button',
                            class: 'mj-btn mj-btn--secondary mj-btn--small',
                            onClick: () => setShowCreateForm(true)
                        }, 
                            (i18n.newRequestFor || 'Nouvelle demande pour %s').replace('%s', memberData.member?.name || 'Animateur')
                        )
                    ),

                    // Year tabs
                    h('div', { class: 'mj-leave-requests__year-tabs' },
                        availableYears.map(year => 
                            h('button', {
                                key: year,
                                type: 'button',
                                class: `mj-leave-requests__year-tab ${year === viewYear ? 'mj-leave-requests__year-tab--active' : ''}`,
                                onClick: () => setViewYear(year)
                            }, year)
                        )
                    ),

                    // Quotas
                    memberData.quotas && h('div', { class: 'mj-leave-requests__quotas mj-leave-requests__quotas--compact' },
                        types.filter(t => memberData.quotas[t.slug] !== undefined).map(type => 
                            h(QuotaCard, { 
                                key: type.slug, 
                                type, 
                                quota: memberData.quotas[type.slug] || 0, 
                                used: memberData.usage?.[type.slug] || 0 
                            })
                        )
                    ),
                    // Calendar overview
                    h(CalendarOverview, {
                        requests: memberData.requests || [],
                        types,
                        onDelete: handleDelete,
                        selectedYear: viewYear,
                        isCoordinator: true,
                        onApprove: handleApprove,
                        onRejectModal: (r) => setRejectingRequest(r)
                    })
                ) : null
            ),

            // Reject Modal
            rejectingRequest && h(RejectModal, {
                request: rejectingRequest,
                onClose: () => setRejectingRequest(null),
                onConfirm: handleReject
            }),

            // Create request for member (Coordinator)
            showCreateForm && selectedAnimateur && h(CoordinatorCreateRequestModal, {
                memberId: selectedAnimateur,
                memberName: memberData?.member?.name || 'Animateur',
                types,
                year: viewYear,
                onClose: () => setShowCreateForm(false),
                onSuccess: () => {
                    setShowCreateForm(false);
                    loadMemberData(selectedAnimateur, viewYear);
                    onRefresh();
                }
            })
        );
    }

    // Coordinator Create Request Modal
    function CoordinatorCreateRequestModal({ memberId, memberName, types, year, onClose, onSuccess }) {
        const i18n = mjLeaveRequests.i18n;
        const [typeId, setTypeId] = useState(null);
        const [dates, setDates] = useState([]);
        const [reason, setReason] = useState('');
        const [loading, setLoading] = useState(false);
        const [error, setError] = useState('');

        const selectedType = useMemo(() => types.find(t => t.id === typeId), [types, typeId]);

        const removeDate = (dateStr) => {
            setDates(prev => prev.filter(d => d !== dateStr));
        };

        const handleSubmit = async () => {
            setError('');

            if (!typeId) {
                setError(i18n.selectType);
                return;
            }
            if (dates.length === 0) {
                setError(i18n.selectDates);
                return;
            }

            setLoading(true);
            try {
                const formData = new FormData();
                formData.append('action', 'mj_leave_request_create_by_coordinator');
                formData.append('nonce', mjLeaveRequests.nonce);
                formData.append('member_id', memberId);
                formData.append('type_id', typeId);
                formData.append('dates', JSON.stringify(dates));
                formData.append('reason', reason);

                const response = await fetch(mjLeaveRequests.ajaxUrl, { method: 'POST', body: formData });
                const result = await response.json();

                if (result.success) {
                    onSuccess();
                } else {
                    setError(result.data?.message || i18n.error);
                }
            } catch (e) {
                setError(i18n.error);
            }
            setLoading(false);
        };

        return h(Modal, { 
            isOpen: true, 
            onClose: loading ? () => {} : onClose,
            title: (i18n.newRequestFor || 'Nouvelle demande pour %s').replace('%s', memberName),
            footer: h(Fragment, null,
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--secondary',
                    onClick: onClose,
                    disabled: loading
                }, i18n.cancel),
                h('button', { 
                    type: 'button', 
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--primary',
                    onClick: handleSubmit,
                    disabled: loading || !typeId || dates.length === 0
                }, loading ? '...' : i18n.submit)
            )
        },
            // Type selector
            h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label mj-leave-requests__form-label--required' }, i18n.selectType),
                h('div', { class: 'mj-leave-requests__type-selector' },
                    types.map(type => {
                        const isSelected = typeId === type.id;
                        const style = isSelected ? {
                            borderColor: type.color,
                            backgroundColor: type.color.substring(0, 7) + '15'
                        } : {};
                        
                        return h('label', { 
                            class: `mj-leave-requests__type-option ${isSelected ? 'mj-leave-requests__type-option--selected' : ''}`,
                            style,
                            key: type.id 
                        },
                            h('input', { 
                                type: 'radio', 
                                name: 'leave_type', 
                                value: type.id,
                                checked: isSelected,
                                onChange: () => setTypeId(type.id)
                            }),
                            h('span', { class: 'mj-leave-requests__type-option-name' }, type.name),
                            !type.requires_validation && h('span', { class: 'mj-leave-requests__type-option-auto' }, i18n.autoApproved)
                        );
                    })
                )
            ),
            // Simple date picker
            h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label mj-leave-requests__form-label--required' }, i18n.selectDates),
                h('div', { style: { marginBottom: '12px' } },
                    h('input', { 
                        type: 'date',
                        class: 'mj-leave-requests__form-input',
                        onChange: (e) => {
                            if (e.target.value && !dates.includes(e.target.value)) {
                                setDates([...dates, e.target.value].sort());
                                e.target.value = '';
                            }
                        }
                    })
                ),
                dates.length > 0 
                    ? h('div', { class: 'mj-leave-requests__selected-dates' },
                        dates.map(d => 
                            h('span', { class: 'mj-leave-requests__selected-date', key: d },
                                formatDateShort(d),
                                h('button', { 
                                    type: 'button', 
                                    class: 'mj-leave-requests__selected-date-remove',
                                    onClick: () => removeDate(d)
                                }, '×')
                            )
                        )
                    )
                    : h('p', { class: 'mj-leave-requests__form-hint' }, i18n.noDatesSelected)
            ),
            // Reason
            h('div', { class: 'mj-leave-requests__form-group' },
                h('label', { class: 'mj-leave-requests__form-label' }, i18n.reason),
                h('textarea', { 
                    class: 'mj-leave-requests__form-textarea',
                    value: reason,
                    onInput: (e) => setReason(e.target.value),
                    rows: 3,
                    placeholder: 'Ex: Formation, repos, etc.'
                })
            ),
            // Error
            error && h('p', { class: 'mj-leave-requests__form-error' }, error)
        );
    }

    // Main App Component
    function LeaveRequestsApp() {
        const config = mjLeaveRequests;
        const i18n = config.i18n;
        const isCoordinator = config.isCoordinator;
        const isAnimateur = config.isAnimateur;
        const workSchedule = config.workSchedule || null;
        const reservedDates = config.reservedDates || {};

        // Check URL for member_id parameter (from notification link)
        const urlParams = new URLSearchParams(window.location.search);
        const urlMemberId = urlParams.get('member_id') ? parseInt(urlParams.get('member_id')) : null;
        const initialTab = isCoordinator && urlMemberId ? 'team' : 'my';

        const currentYear = new Date().getFullYear();
        const [tab, setTab] = useState(initialTab);
        const [showForm, setShowForm] = useState(false);
        const [ownRequests, setOwnRequests] = useState(config.ownRequests || []);
        const [pendingRequests, setPendingRequests] = useState(config.pendingRequests || []);
        const [usage, setUsage] = useState(config.usage || {});
        const [quotas, setQuotas] = useState(config.quotas || {});
        const [rejectingRequest, setRejectingRequest] = useState(null);
        const [loading, setLoading] = useState(false);
        const [selectedYear, setSelectedYear] = useState(config.year || currentYear);

        const types = config.types || [];
        const availableYears = [currentYear - 2, currentYear - 1, currentYear, currentYear + 1];

        const refreshData = async (year = null) => {
            setLoading(true);
            try {
                const result = await api.refresh(year || selectedYear);
                if (result.success) {
                    setOwnRequests(result.data.ownRequests || []);
                    setPendingRequests(result.data.pendingRequests || []);
                    setUsage(result.data.usage || {});
                    if (result.data.quotas) {
                        setQuotas(result.data.quotas);
                    }
                    if (result.data.year) {
                        setSelectedYear(result.data.year);
                    }
                }
            } catch (e) {
                console.error('Failed to refresh leave requests', e);
            }
            setLoading(false);
        };

        const handleYearChange = (year) => {
            setSelectedYear(year);
            refreshData(year);
        };

        const handleCreateSuccess = (data) => {
            setShowForm(false);
            if (data.request) {
                setOwnRequests(prev => [data.request, ...prev]);
            }
            refreshData();
        };

        const handleCancel = async (requestId) => {
            if (!confirm(i18n.confirmCancel)) return;

            const result = await api.cancel(requestId);
            if (result.success) {
                setOwnRequests(prev => prev.filter(r => r.id !== requestId));
                setPendingRequests(prev => prev.filter(r => r.id !== requestId));
            } else {
                alert(result.data?.message || i18n.error);
            }
        };

        const handleApprove = async (request) => {
            const result = await api.approve(request.id);
            if (result.success) {
                refreshData();
            }
        };

        const handleReject = async (requestId, comment) => {
            const result = await api.reject(requestId, comment);
            if (result.success) {
                setRejectingRequest(null);
                refreshData();
            }
        };

        const myPending = ownRequests.filter(r => r.status === 'pending');
        const myProcessed = ownRequests.filter(r => r.status !== 'pending');

        return h('div', { class: 'mj-leave-requests' },
            // Header
            h('div', { class: 'mj-leave-requests__header' },
                h('h2', { class: 'mj-leave-requests__title' }, i18n.title),
                isAnimateur && h('button', { 
                    type: 'button',
                    class: 'mj-leave-requests__btn mj-leave-requests__btn--primary',
                    onClick: () => setShowForm(true)
                }, '+ ' + i18n.newRequest)
            ),

            // Quotas (for animateurs) with year selector
            isAnimateur && Object.keys(quotas).length > 0 && h('div', { class: 'mj-leave-requests__quotas-section' },
                h('div', { class: 'mj-leave-requests__year-tabs' },
                    availableYears.map(year => 
                        h('button', {
                            key: year,
                            type: 'button',
                            class: `mj-leave-requests__year-tab ${year === selectedYear ? 'mj-leave-requests__year-tab--active' : ''}`,
                            onClick: () => handleYearChange(year)
                        }, year)
                    )
                ),
                h('div', { class: 'mj-leave-requests__quotas' },
                    types.filter(t => quotas[t.slug] !== undefined).map(type => 
                        h(QuotaCard, { 
                            key: type.slug, 
                            type, 
                            quota: quotas[type.slug] || 0, 
                            used: usage[type.slug] || 0 
                        })
                    )
                )
            ),

            // Tabs (only for coordinators who have multiple tabs)
            isCoordinator && h('div', { class: 'mj-leave-requests__tabs' },
                h('button', { 
                    type: 'button',
                    class: `mj-leave-requests__tab ${tab === 'my' ? 'mj-leave-requests__tab--active' : ''}`,
                    onClick: () => setTab('my')
                }, 
                    i18n.myRequests,
                    myPending.length > 0 && h('span', { class: 'mj-leave-requests__tab-badge' }, myPending.length)
                ),
                h('button', { 
                    type: 'button',
                    class: `mj-leave-requests__tab ${tab === 'team' ? 'mj-leave-requests__tab--active' : ''}`,
                    onClick: () => setTab('team')
                }, 
                    i18n.teamView || 'Vue équipe',
                    pendingRequests.length > 0 && h('span', { class: 'mj-leave-requests__tab-badge' }, pendingRequests.length)
                )
            ),

            // Content
            loading ? h('div', { class: 'mj-leave-requests__loading' }, h('div', { class: 'mj-leave-requests__spinner' })) :
            h('div', { class: 'mj-leave-requests__content' },
                // My requests - always shown for animateurs (no tabs), or when 'my' tab selected for coordinators
                (tab === 'my' || (!isCoordinator && isAnimateur)) && h(Fragment, null,
                    // Calendar Overview with 3 months
                    h(CalendarOverview, {
                        requests: ownRequests,
                        types,
                        onDelete: handleCancel,
                        selectedYear
                    })
                ),
                tab === 'team' && isCoordinator && h(CoordinatorView, {
                    types,
                    animateurs: config.animateurs || [],
                    pendingRequests,
                    onApprove: handleApprove,
                    onReject: (requestId, comment) => handleReject(requestId, comment),
                    onRefresh: refreshData,
                    initialMemberId: urlMemberId,
                    selectedYear
                })
            ),

            // Create Form Modal
            showForm && h(Modal, { 
                isOpen: true, 
                onClose: () => setShowForm(false), 
                title: i18n.newRequest 
            }, 
                h(LeaveRequestForm, { 
                    onClose: () => setShowForm(false), 
                    onSuccess: handleCreateSuccess,
                    types,
                    quotas,
                    usage,
                    workSchedule,
                    reservedDates
                })
            ),

            // Reject Modal
            rejectingRequest && h(RejectModal, {
                request: rejectingRequest,
                onClose: () => setRejectingRequest(null),
                onConfirm: handleReject
            })
        );
    }

    // Initialize widget
    function init() {
        const containers = document.querySelectorAll('[data-mj-leave-requests-widget]');
        containers.forEach(container => {
            if (container.dataset.mjInit) return;
            container.dataset.mjInit = 'true';
            render(h(LeaveRequestsApp), container);
        });
    }

    // Run on DOMContentLoaded and also check for dynamically added elements
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Support for Elementor editor preview
    if (window.elementorFrontend) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/mj-member-leave-requests.default', init);
    }
})();
