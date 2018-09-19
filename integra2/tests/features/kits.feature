Feature: Kits
  In order to simplify the SKUs for product kits
  As an admin
  I want to be able to create SKUs that contain one or more products of varying quantities

  Scenario: With Valid Credentials
    Given I am on "kits/create"
    And I fill in "name" with "Engine Oil with 4 Spark Plugs"
    And I fill in "mpn1" with "BKR5EGP"
    And I fill in "qty1" with "4"
    And I fill in "mpn2" with "2004"
    And I fill in "qty2" with "1"
    And I press "Submit"
    Then I should see "Engine Oil with 4 Spark Plugs"