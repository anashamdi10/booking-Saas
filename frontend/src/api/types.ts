export type UserRole = 'admin' | 'photographer'
export type UserStatus = 'pending' | 'active' | 'suspended'
export type BookingStatus = 'pending' | 'confirmed' | 'completed' | 'cancelled' | 'rejected'

export interface User {
  id: number
  name: string
  email: string
  phone: string | null
  role: UserRole
  status: UserStatus
  avatar_url: string | null
  created_at: string | null
}

export interface ThemeColors {
  primary: string
  accent: string
  background: string
  surface: string
  text: string
}

export interface Photographer {
  id: number
  slug: string
  name: string
  headline: string | null
  bio: string | null
  city: string | null
  country: string
  logo_url: string | null
  cover_url: string | null
  theme: ThemeColors
  landing_template: string
  years_experience: number | null
  rating_avg: number
  rating_count: number
  is_featured: boolean
  packages_count: number
}

export interface Package {
  id: number
  name: string
  slug: string
  description: string | null
  price: number
  currency: string
  duration_minutes: number
  max_photos: number | null
  deliverables: string[]
  is_active: boolean
  sort_order: number
}

export interface WorkingHour {
  weekday: number
  start_time: string
  end_time: string
}

export interface PortfolioImage {
  id: number
  url: string
  thumbnail_url: string | null
  width: number | null
  height: number | null
}

export interface Slot {
  start: string
  end: string
  label: string
}

export interface AvailabilityDay {
  date: string
  slots: Slot[]
}

export interface Booking {
  id: number
  reference: string
  photographer_id: number
  photographer_name: string | null
  package: { id: number; name: string; duration_minutes: number } | null
  customer_name: string
  customer_email: string
  customer_phone: string | null
  start_at: string
  end_at: string
  status: BookingStatus
  status_label: string
  source: string | null
  notes: string | null
  total_price: number | null
  currency: string
  created_at: string | null
}

export interface SiteStats {
  photographers_count: number
  cities_count: number
  bookings_count: number
  completed_bookings_count: number
  visits_total: number
  visits_today: number
  invoices_paid_total: number
}

export interface PhotographerDetail {
  profile: Photographer
  packages: Package[]
  working_hours: WorkingHour[]
  portfolio: PortfolioImage[]
  whatsapp_url: string | null
  bookings_count: number
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface Plan {
  id: number
  name: string
  slug: string
  description: string | null
  price: number
  currency: string
  storage_limit_bytes: number
  storage_limit_gb: number
  max_galleries: number | null
  max_clients: number | null
  max_bookings_per_month: number | null
  features: string[]
  is_active: boolean
  sort_order: number
  /** Present only when the caller asked for it — blocks deleting a plan in use. */
  subscriptions_count?: number
}

/**
 * Notification stored for the authenticated photographer (bell in the sidebar).
 */
export interface AppNotification {
  id: string
  type: string
  title: string
  reference: string | null
  customer_name: string | null
  start_at: string | null
  package_name: string | null
  url: string | null
  read_at: string | null
  created_at: string | null
}

export interface NotificationListResponse {
  data: AppNotification[]
  meta: { unread: number }
}
