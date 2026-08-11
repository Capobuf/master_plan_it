import type React from "react";
import { useEffect, useRef } from "react";

interface DropdownProps {
  isOpen: boolean;
  onClose: () => void;
  children: React.ReactNode;
  className?: string;
  triggerId?: string;
}

export const Dropdown: React.FC<DropdownProps> = ({
  isOpen,
  onClose,
  children,
  className = "",
  triggerId,
}) => {
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      const target = event.target as HTMLElement;
      const trigger = target.closest<HTMLElement>("[aria-controls]");
      const clickedOwnTrigger = triggerId
        ? trigger?.getAttribute("aria-controls") === triggerId
        : target.closest(".dropdown-toggle") !== null;

      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(target) &&
        !clickedOwnTrigger
      ) {
        onClose();
      }
    };

    document.addEventListener("mousedown", handleClickOutside);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, [onClose, triggerId]);

  if (!isOpen) return null;

  return (
    <div
      id={triggerId}
      ref={dropdownRef}
      className={`absolute z-40  right-0 mt-2  rounded-xl border border-gray-200 bg-white  shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark ${className}`}
    >
      {children}
    </div>
  );
};
