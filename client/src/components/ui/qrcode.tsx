"use client"

import * as React from "react"
import QRCode from "qrcode.react"

interface QRCodeSVGProps {
  value: string
  size?: number
  level?: "L" | "M" | "Q" | "H"
  includeMargin?: boolean
}

export function QRCodeSVG({ value, size = 200, level = "M", includeMargin = true }: QRCodeSVGProps) {
  return (
    <div className="flex justify-center">
      <QRCode
        value={value}
        size={size}
        level={level}
        includeMargin={includeMargin}
        bgColor="#ffffff"
        fgColor="#000000"
      />
    </div>
  )
}
