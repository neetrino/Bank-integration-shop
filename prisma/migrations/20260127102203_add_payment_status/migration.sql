-- CreateEnum
CREATE TYPE "PaymentStatus" AS ENUM ('PENDING', 'SUCCESS', 'FAILED', 'REFUNDED', 'CANCELLED');

-- AlterTable
ALTER TABLE "orders" ADD COLUMN     "paymentStatus" "PaymentStatus";
