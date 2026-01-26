import type { Metadata } from "next";
import { Inter } from "next/font/google";
import "./globals.css";
import ClientProviders from "@/components/ClientProviders";
import MobileBottomNav from "@/components/MobileBottomNav";
import ServiceWorkerProvider from "@/components/ServiceWorkerProvider";
import PullToRefresh from "@/components/PullToRefresh";
import Header from "@/components/Header";
import ChatButton from "@/components/ChatButton";

const inter = Inter({
  subsets: ["latin"],
  variable: "--font-inter",
});

export const metadata: Metadata = {
  title: "WelcomeBaby",
  description: "Որակյալ Արտադրանք Ձեր Երեխաների Համար",
  keywords: "մանկական, խաղալիքներ, մանկական արտադրանք, Երևան, Հայաստան",
  icons: {
    icon: [
      { url: '/logo.ico', sizes: 'any' },
      { url: '/images/logo.png', type: 'image/png' },
    ],
    shortcut: '/logo.ico',
    apple: '/images/logo.png',
  },
  manifest: '/manifest.json',
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="ru" data-scroll-behavior="smooth" suppressHydrationWarning>
      <body className={`${inter.variable} font-sans antialiased overflow-x-hidden bg-white`} suppressHydrationWarning>
        <ServiceWorkerProvider />
        <ClientProviders>
          <Header />
          <PullToRefresh>
            {children}
          </PullToRefresh>
          <MobileBottomNav />
          
          {/* Глобальная плавающая кнопка чата - зафиксирована в правом нижнем углу экрана */}
          <ChatButton 
            instagramUrl="https://www.instagram.com/welcome_baby_armenia/?utm_source=neetrino.com"
            facebookUrl="https://www.facebook.com/welcomebaby.yerevan"
          />
        </ClientProviders>
      </body>
    </html>
  );
}
