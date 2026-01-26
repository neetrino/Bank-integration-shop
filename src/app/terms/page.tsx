// Редирект на полную версию страницы
import { redirect } from 'next/navigation'

export default function TermsPage() {
  redirect('/terms-and-conditions')
}
