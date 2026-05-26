import * as React from "react"

import { cn } from "@/lib/utils"

function Input({
  className,
  type,
  ...props
}) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "file:text-slate-900 placeholder:text-slate-500 selection:bg-primary selection:text-primary-foreground flex h-10 w-full min-w-0 rounded-lg border border-slate-300/90 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs transition-[border-color,box-shadow,background-color,color] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:file:text-slate-100 dark:placeholder:text-slate-400 dark:selection:bg-primary dark:selection:text-primary-foreground",
        "focus-visible:border-primary/80 focus-visible:ring-primary/25 focus-visible:ring-[3px] dark:focus-visible:border-primary/70 dark:focus-visible:ring-primary/35",
        "aria-invalid:border-red-500 aria-invalid:ring-red-500/20 dark:aria-invalid:border-red-400 dark:aria-invalid:ring-red-400/30",
        className
      )}
      {...props} />
  );
}

export { Input }
