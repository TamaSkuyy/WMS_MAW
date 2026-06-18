import type React from "react";
import type { FC } from "react";

interface InputProps {
  type?: "text" | "number" | "email" | "password" | "date" | "time" | string;
  id?: string;
  name?: string;
  placeholder?: string;
  value?: string | number;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
  className?: string;
  min?: string;
  max?: string;
  step?: number;
  disabled?: boolean;
  success?: boolean;
  error?: boolean;
  hint?: string;
}

const Input: FC<InputProps> = ({
  type = "text",
  id,
  name,
  placeholder,
  value,
  onChange,
  className = "",
  min,
  max,
  step,
  disabled = false,
  success = false,
  error = false,
  hint,
}) => {
  let inputClasses = `w-full border border-[#DEE2E6] rounded-lg px-3.5 py-2.5 text-sm text-[#1A1D23] placeholder-[#ADB5BD] bg-white focus:border-[#3B5BDB] focus:ring-2 focus:ring-[#3B5BDB]/20 transition-all duration-150 ${className}`;

  if (disabled) {
    inputClasses += ` text-gray-500 border-gray-300 opacity-40 bg-gray-100 cursor-not-allowed`;
  } else if (error) {
    inputClasses += ` border-[#FA5252] ring-[#FA5252]/20`;
  } else if (success) {
    inputClasses += ` border-[#40C057] ring-[#40C057]/20`;
  } else {
    inputClasses += ``;
  }

  return (
    <div className="relative">
      <input
        type={type}
        id={id}
        name={name}
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        min={min}
        max={max}
        step={step}
        disabled={disabled}
        className={inputClasses}
      />

      {hint && (
        <p
          className={`mt-1.5 text-xs ${
            error
              ? "text-[#FA5252]"
              : success
              ? "text-[#40C057]"
              : "text-[#6C757D]"
          }`}
        >
          {hint}
        </p>
      )}
    </div>
  );
};

export default Input;
