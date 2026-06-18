import { ReactNode } from "react";

interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  children: ReactNode;
  size?: "sm" | "md";
  variant?: "primary" | "outline" | "danger";
  startIcon?: ReactNode;
  endIcon?: ReactNode;
  icon?: ReactNode;
  className?: string;
}

const Button: React.FC<ButtonProps> = ({
  children,
  size = "md",
  variant = "primary",
  startIcon,
  endIcon,
  icon,
  className = "",
  ...props
}) => {
  const sizeClasses = {
    sm: "px-3 py-1.5 text-xs",
    md: "px-4 py-2.5 text-sm",
  };

  const variantClasses = {
    primary:
      "bg-gradient-to-r from-[#3B5BDB] to-[#4DABF7] text-white hover:brightness-110 shadow-sm",
    outline:
      "border border-[#DEE2E6] text-[#6C757D] hover:bg-[#F8F9FC]",
    danger:
      "bg-[#FA5252] text-white hover:bg-[#E03131]",
  };

  return (
    <button
      className={`inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-all duration-150 ${className} ${
        sizeClasses[size]
      } ${variantClasses[variant]} ${
        props.disabled ? "cursor-not-allowed opacity-50" : ""
      }`}
      {...props}
    >
      {icon && <span className="flex items-center">{icon}</span>}
      {startIcon && <span className="flex items-center">{startIcon}</span>}
      {children}
      {endIcon && <span className="flex items-center">{endIcon}</span>}
    </button>
  );
};

export default Button;
