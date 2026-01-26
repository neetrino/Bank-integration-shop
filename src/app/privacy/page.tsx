// Редирект на полную версию страницы
import { redirect } from 'next/navigation'

export default function PrivacyPage() {
  redirect('/privacy-policy')
}
