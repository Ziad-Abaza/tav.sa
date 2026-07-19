import { useTimeAgo } from '@vueuse/core'
import { computed } from 'vue'

/**
 * Get timezone-aware relative time
 * Note: Relative time is always calculated from absolute timestamps,
 * so "20 minutes ago" will be the same in any timezone.
 * Use useDateFormat for timezone-specific absolute dates.
 */
export function useTimezoneAgo(date) {
    // For relative time, just use the original date - timezone doesn't affect "ago"
    return useTimeAgo(date)
}

/**
 * Format date in configured timezone
 * For absolute date display like "Jan 16, 2026 14:30 IST"
 */
export function useTimezoneFormat(date, includeTime = true) {
    const timezone = window.dateTimeSettings?.timezone || 'UTC'
    const dateFormat = window.dateTimeSettings?.dateFormat || 'd-m-Y'
    let timeFormat = window.dateTimeSettings?.timeFormat || 'H:i'

    // Convert simple "12" or "24" to proper PHP time format
    if (timeFormat === '12') {
        timeFormat = 'h:i A'
    } else if (timeFormat === '24') {
        timeFormat = 'H:i'
    }

    return computed(() => {
        if (!date) return ''
        
        // Unwrap computed refs or reactive values
        let actualDate = date
        if (typeof date === 'object' && date !== null && 'value' in date) {
            actualDate = date.value
        }
        
        if (!actualDate) return ''

        try {
            const dateObj = new Date(actualDate)
            
            // Check if the date is valid
            if (isNaN(dateObj.getTime())) {
                console.warn('Invalid date value:', actualDate)
                return String(actualDate) || ''
            }

            // Get date parts in the specified timezone
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            })

            const parts = formatter.formatToParts(dateObj)
            const dateParts = {}
            parts.forEach(part => {
                dateParts[part.type] = part.value
            })

            // Build formatted string based on PHP format
            let result = dateFormat

            // Replace date tokens
            result = result.replace('Y', dateParts.year) // 4-digit year
            result = result.replace('y', dateParts.year.slice(-2)) // 2-digit year
            result = result.replace('m', dateParts.month) // Month with leading zero
            result = result.replace('n', parseInt(dateParts.month)) // Month without leading zero
            result = result.replace('d', dateParts.day) // Day with leading zero
            result = result.replace('j', parseInt(dateParts.day)) // Day without leading zero

            // Add month names if needed
            if (dateFormat.includes('F') || dateFormat.includes('M')) {
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                    'July', 'August', 'September', 'October', 'November', 'December']
                const monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
                const monthIndex = parseInt(dateParts.month) - 1
                result = result.replace('F', monthNames[monthIndex])
                result = result.replace('M', monthShort[monthIndex])
            }

            // Add time if requested
            if (includeTime) {
                let timeStr = timeFormat
                const hour = parseInt(dateParts.hour)

                if (timeFormat.includes('h') || timeFormat.includes('g')) {
                    // 12-hour format
                    const hour12 = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour)
                    timeStr = timeStr.replace('h', hour12.toString().padStart(2, '0'))
                    timeStr = timeStr.replace('g', hour12.toString())
                    timeStr = timeStr.replace('a', hour >= 12 ? 'pm' : 'am')
                    timeStr = timeStr.replace('A', hour >= 12 ? 'PM' : 'AM')
                } else {
                    // 24-hour format
                    timeStr = timeStr.replace('H', dateParts.hour)
                    timeStr = timeStr.replace('G', parseInt(dateParts.hour).toString())
                }

                timeStr = timeStr.replace('i', dateParts.minute)
                timeStr = timeStr.replace('s', dateParts.second)

                result += ' ' + timeStr
            }

            return result
        } catch (error) {
            console.error('Error formatting date:', error, 'Input:', actualDate)
            return String(actualDate) || ''
        }
    })
}

/**
 * Format date only (without time) in configured timezone
 * For display like "Jan 16, 2026"
 */
export function useTimezoneDateOnly(date) {
    return useTimezoneFormat(date, false)
}

/**
 * Format time only in configured timezone
 * For display like "14:30" or "2:30 PM"
 */
export function useTimezoneTimeOnly(date) {
    const timezone = window.dateTimeSettings?.timezone || 'UTC'
    let timeFormat = window.dateTimeSettings?.timeFormat || 'H:i'

    // Convert simple "12" or "24" to proper PHP time format
    if (timeFormat === '12') {
        timeFormat = 'h:i A'
    } else if (timeFormat === '24') {
        timeFormat = 'H:i'
    }

    return computed(() => {
        if (!date) return ''
        
        // Unwrap computed refs or reactive values
        let actualDate = date
        if (typeof date === 'object' && date !== null && 'value' in date) {
            actualDate = date.value
        }
        
        if (!actualDate) return ''

        try {
            const dateObj = new Date(actualDate)
            
            // Check if the date is valid
            if (isNaN(dateObj.getTime())) {
                console.warn('Invalid date value:', actualDate)
                return String(actualDate) || ''
            }

            // Get time parts in the specified timezone
            const formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: timezone,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            })

            const parts = formatter.formatToParts(dateObj)
            const timeParts = {}
            parts.forEach(part => {
                timeParts[part.type] = part.value
            })

            // Build formatted string based on PHP time format
            let result = timeFormat
            const hour = parseInt(timeParts.hour)

            if (timeFormat.includes('h') || timeFormat.includes('g')) {
                // 12-hour format
                const hour12 = hour === 0 ? 12 : (hour > 12 ? hour - 12 : hour)
                result = result.replace('h', hour12.toString().padStart(2, '0'))
                result = result.replace('g', hour12.toString())
                result = result.replace('a', hour >= 12 ? 'pm' : 'am')
                result = result.replace('A', hour >= 12 ? 'PM' : 'AM')
            } else {
                // 24-hour format
                result = result.replace('H', timeParts.hour)
                result = result.replace('G', parseInt(timeParts.hour).toString())
            }

            result = result.replace('i', timeParts.minute)
            result = result.replace('s', timeParts.second)

            return result
        } catch (error) {
            console.error('Error formatting time:', error, 'Input:', actualDate)
            return String(actualDate) || ''
        }
    })
}
