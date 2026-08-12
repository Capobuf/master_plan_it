import { render, screen } from "@testing-library/react";
import { expect, it } from "vitest";
import PlafondMeasures from "./PlafondMeasures";

it("renders all four server-provided official measures verbatim without a local residual", () => {
  render(<PlafondMeasures currency="EUR" measures={{ allocation: { net: "1", vat: "2", gross: "3", official: "3500.01" }, coverage_planned: { net: "1", vat: "2", gross: "3", official: "4200.02" }, consumed: { net: "1", vat: "2", gross: "3", official: "2500.03" }, available: { net: "1", vat: "2", gross: "3", official: "999.98" } }} />);
  expect(screen.getByText("3.500,01 €")).toBeInTheDocument();
  expect(screen.getByText("4.200,02 €")).toBeInTheDocument();
  expect(screen.getByText("2.500,03 €")).toBeInTheDocument();
  expect(screen.getByText("999,98 €")).toBeInTheDocument();
  expect(screen.queryByText(/Sforamento/i)).not.toBeInTheDocument();
});
