/**
 * System (OS level) notifications for the visitor who just booked.
 *
 * The Web Notifications API only exists in secure contexts, can only be
 * triggered from a user gesture the first time, and dies with the page — so
 * every helper here degrades to a silent no-op instead of throwing.
 */

export type NotificationPermissionState = 'unsupported' | 'default' | 'granted' | 'denied'

function supported(): boolean {
  return typeof window !== 'undefined' && 'Notification' in window
}

export function notificationPermission(): NotificationPermissionState {
  if (!supported()) return 'unsupported'

  return Notification.permission as NotificationPermissionState
}

/**
 * Asks the browser for permission. Must be called from a click handler:
 * called outside a gesture Chrome answers "denied" without showing a prompt.
 */
export async function requestPermission(): Promise<NotificationPermissionState> {
  if (!supported()) return 'unsupported'
  if (Notification.permission !== 'default') {
    return Notification.permission as NotificationPermissionState
  }

  try {
    return (await Notification.requestPermission()) as NotificationPermissionState
  } catch {
    return 'denied'
  }
}

/**
 * Fires the notification when permission was already granted. Returns whether
 * one was actually shown so the caller can report an honest result.
 */
export function notify(title: string, body: string): boolean {
  if (!supported() || Notification.permission !== 'granted') return false

  try {
    // `tag` collapses repeats so re-submitting never stacks notifications.
    new Notification(title, { body, tag: 'booking' })

    return true
  } catch {
    return false
  }
}
