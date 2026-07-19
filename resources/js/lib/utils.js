/**
 * Simple class merger utility (cn helper).
 * Filters out falsy values and joins class strings.
 * Used by shadcn-style Vue table primitives.
 */
export function cn(...classes) {
    return classes.filter(Boolean).join(' ')
}
