# ADR 0001: One client = one Frappe site (multi-site tenancy)

Date: 2025-12-21

## Status
Accepted

## Context
We need hard segregation between clients. The vCIO serves many clients, while clients must only see their own data. A single shared site with user permissions increases complexity and risk.

## Decision
Adopt a multi-site model: each client is a separate Frappe site (tenant).

## Consequences
- Data is isolated per tenant (DB separation).
- vCIO accounts must exist per site (operational overhead).
- Cross-tenant analytics requires external aggregation (out of scope V1).

